<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FrozenDataLockedException;
use App\Http\Controllers\Controller;
use App\Repositories\FrozenDataRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RisParseController extends Controller
{
    private const WEBHOOK_URL = 'https://n8n.srv796541.hstgr.cloud/webhook/parse-pdf-salaire';
    private const FRF_TO_EUR  = 6.55957;

    public function __construct(private FrozenDataRepository $repository)
    {
    }

    /**
     * POST /api/parse-ris
     *
     * Reçoit un PDF RIS, l'envoie au webhook n8n, mappe les données
     * carrière et les sauvegarde dans frozen_data.
     *
     * Body : multipart/form-data
     *   - file     : PDF (required)
     *   - user_id  : integer (required)
     */
    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => 'required|file|mimes:pdf|max:20480',
            'user_id' => 'required|integer',
        ]);

        $userId = (int) $request->input('user_id');
        $file   = $request->file('file');

        // ── 1. Appel n8n ────────────────────────────────────────────────────
        $n8nResponse = Http::timeout(300)
            ->attach('file0', file_get_contents($file->getPathname()), $file->getClientOriginalName())
            ->post(self::WEBHOOK_URL);

        if (!$n8nResponse->successful()) {
            return response()->json(['message' => 'Le webhook RIS a retourné une erreur.'], 502);
        }

        $risData = $n8nResponse->json();
        if (is_array($risData) && isset($risData[0])) {
            $risData = $risData[0];
        }

        \Log::info('[RIS] keys=' . json_encode(array_keys($risData ?? [])));
        \Log::info('[RIS] carriere[0]=' . json_encode(($risData['carriere'] ?? [])[0] ?? null));

        // ── 2. Détection du format (nouveau vs ancien) ───────────────────────
        $isNewFormat = isset($risData['profil']) && isset($risData['carriere']);

        if ($isNewFormat) {
            // ── Nouveau format : { profil, carriere: [{annee, revenus, regimes}] }
            $carriereRaw = $risData['carriere'] ?? [];
            if (empty($carriereRaw)) {
                return response()->json(['message' => 'Le RIS ne contient aucune ligne de carrière exploitable.'], 422);
            }

            $carriere = [];
            foreach ($carriereRaw as $entry) {
                // Année : "annee" (Vision) ou "year" (Flash)
                $annee = (int) ($entry['annee'] ?? $entry['year'] ?? 0);
                if (!$annee) {
                    continue;
                }
                // Revenus : "revenus_total", "revenus", ou "revenu"
                $revenus = (int) ($entry['revenus_total'] ?? $entry['revenus'] ?? $entry['revenu'] ?? 0);
                // Régimes : strings ou objets {nom, ...} → on normalise en strings
                $regimes = array_map(
                    fn($r) => is_array($r) ? ($r['nom'] ?? '') : (string) $r,
                    $entry['regimes'] ?? []
                );
                $carriere[] = [
                    'annee'        => $annee,
                    'sal_original' => $revenus,
                    'sal_eur'      => $revenus,
                    'devise'       => '€',
                    'regimes'      => array_values(array_filter($regimes)),
                ];
            }

            if (empty($carriere)) {
                return response()->json(['message' => 'Le RIS ne contient aucune ligne de carrière exploitable.'], 422);
            }

            usort($carriere, fn($a, $b) => $b['annee'] - $a['annee']);

            $meta   = $risData['profil'] ?? null;
            $totaux = null;
            $points = null;
        } else {
            // ── Ancien format : { trimestres, detail_carriere, personne, points }
            if (empty($risData['trimestres']) || !isset($risData['detail_carriere'])) {
                return response()->json(['message' => 'Ce document ne semble pas être un RIS.'], 422);
            }

            $detailCarriere = $risData['detail_carriere'] ?? [];
            if (empty($detailCarriere)) {
                return response()->json(['message' => 'Le RIS ne contient aucune ligne de carrière exploitable.'], 422);
            }

            // ── 3. Mapping detail_carriere → rows par année ──────────────────
            $yearMap = [];
            foreach ($detailCarriere as $entry) {
                if (empty($entry['date_debut']) || !isset($entry['revenus'])) {
                    continue;
                }
                $parts = explode('/', $entry['date_debut']);
                $annee = (int) ($parts[2] ?? 0);
                if (!$annee) {
                    continue;
                }

                if (!isset($yearMap[$annee])) {
                    $yearMap[$annee] = ['entries' => [], 'devise' => $entry['devise'] ?? '€'];
                }
                if (($entry['devise'] ?? '') === '€') {
                    $yearMap[$annee]['devise'] = '€';
                }

                $key = ($entry['employeur'] ?? '') . '__' . $entry['revenus'];
                if (!isset($yearMap[$annee]['entries'][$key])) {
                    $eur = ($entry['devise'] ?? '') === 'FRF'
                        ? (int) round($entry['revenus'] / self::FRF_TO_EUR)
                        : (int) $entry['revenus'];

                    $yearMap[$annee]['entries'][$key] = [
                        'original' => (int) $entry['revenus'],
                        'eur'      => $eur,
                    ];
                }
            }

            // ── 4. Construire le tableau carrière final ──────────────────────
            $carriere = [];
            foreach ($yearMap as $annee => $bucket) {
                $salOriginal = array_sum(array_column($bucket['entries'], 'original'));
                $salEur      = array_sum(array_column($bucket['entries'], 'eur'));

                $carriere[] = [
                    'annee'        => $annee,
                    'sal_original' => $salOriginal,
                    'sal_eur'      => $salEur,
                    'devise'       => $bucket['devise'],
                ];
            }

            usort($carriere, fn($a, $b) => $b['annee'] - $a['annee']);

            $meta   = $risData['personne'] ?? null;
            $totaux = $risData['trimestres'] ?? null;
            $points = $risData['points'] ?? null;
        }

        // ── 5. Sauvegarde dans frozen_data ───────────────────────────────────

        try {
            $this->repository->createOrUpdate($userId, [
                'user_id'  => $userId,
                'source'   => 'RIS_PARSE_' . now()->format('Y'),
                'meta'     => $meta,
                'carriere' => $carriere,
                'alertes'  => [],
                'totaux'   => array_merge((array) $totaux, ['points' => $points]),
            ]);
        } catch (FrozenDataLockedException $e) {
            return response()->json(['message' => $e->getMessage()], 423);
        }

        // ── 6. Réponse au frontend ───────────────────────────────────────────
        return response()->json([
            'carriere' => $carriere,
            'totaux'   => $totaux,
            'meta'     => $meta,
        ], 200);
    }
}
