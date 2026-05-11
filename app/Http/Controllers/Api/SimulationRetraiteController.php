<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\FrozenData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SimulationRetraiteController extends Controller
{
    private const SKILL_ID    = 'simulation_retraite';
    private const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/simulation-retraite';
    private const PAYLOAD_VERSION = '2';

    /**
     * POST /api/v1/simulation-retraite/generate
     *
     * Charge frozen_data + AnalysisReport des autres skills,
     * construit un payload riche, l'envoie à n8n et stocke le HTML retourné.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        $clientId = (int) $request->input('client_id');
        $revenuSouhaite = (float) $request->input('revenu_souhaite', 0);

        $frozen = FrozenData::where('user_id', $clientId)->latest()->first();

        if (! $frozen) {
            return response()->json([
                'error' => 'Données carrière introuvables pour ce client. Veuillez d\'abord valider la carrière.',
            ], 404);
        }

        $resolvedMeta = self::resolveMeta($frozen->meta ?? [], $clientId);

        $calculsSkills = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', '!=', self::SKILL_ID)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => [
                'skill_id'     => $r->skill_id,
                'result_json'  => $r->result_json,
                'calcul_json'  => $r->calcul_json,
                'alertes_json' => $r->alertes_json ?? [],
                'statut'       => $r->statut,
                'updated_at'   => $r->updated_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        $payload = self::buildN8nPayload(
            $frozen,
            $clientId,
            $revenuSouhaite,
            $resolvedMeta,
            $calculsSkills
        );

        try {
            $n8nResponse = Http::timeout(900)->post(self::N8N_WEBHOOK, $payload);

            $body = $n8nResponse->json() ?? [];

            if (! empty($body['html_report'])) {
                AnalysisReport::updateOrCreate(
                    [
                        'user_id'  => $clientId,
                        'skill_id' => self::SKILL_ID,
                        'statut'   => 'brouillon',
                    ],
                    [
                        'frozen_data_id'      => $frozen->id,
                        'result_json'         => $body['html_report'],
                        'calcul_json'         => $body['calcul_json'] ?? [],
                        'restitution_json'    => [],
                        'alertes_json'        => [],
                        'arret_critique_json' => [],
                        'statut'              => 'brouillon',
                    ]
                );
            }

            return response()->json([
                'success'     => $n8nResponse->successful(),
                'html_report' => $body['html_report'] ?? null,
                'client_id'   => $clientId,
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Construit le payload envoyé à n8n. Pure : pas d'accès DB, pas d'I/O.
     * Toutes les données nécessaires sont passées en paramètres.
     */
    public static function buildN8nPayload(
        FrozenData $frozen,
        int $clientId,
        float $revenuSouhaite,
        array $resolvedMeta,
        array $calculsSkills
    ): array {
        $carriere = $frozen->carriere ?? [];
        $totaux   = self::enrichTotaux($frozen->totaux ?? [], $carriere);

        $scenariosRetenus = self::normalizeArrayWithFallback(
            $frozen->scenarios_choisis,
            $frozen->scenario_choisi
        );

        $datesRetenues = self::normalizeArrayWithFallback(
            $frozen->dates_retenues,
            $frozen->date_retenue
        );

        return [
            'version'         => self::PAYLOAD_VERSION,
            'client_id'       => $clientId,
            'generated_at'    => Carbon::now()->toIso8601String(),
            'revenu_souhaite' => $revenuSouhaite,

            'client' => [
                'user_id'        => $clientId,
                'nom'            => $resolvedMeta['nom']            ?? null,
                'prenom'         => $resolvedMeta['prenom']         ?? null,
                'date_naissance' => $resolvedMeta['date_naissance'] ?? null,
                'sexe'           => $resolvedMeta['sexe']           ?? null,
                'nir'            => $resolvedMeta['nir']            ?? null,
                'enfants'        => $resolvedMeta['enfants']        ?? null,
            ],

            'frozen' => [
                'id'        => $frozen->id,
                'source'    => $frozen->source,
                'locked'    => $frozen->isLocked(),
                'locked_at' => $frozen->locked_at?->toIso8601String(),
                'locked_by' => $frozen->locked_by,
            ],

            'carriere' => $carriere,

            'totaux' => $totaux,

            'regimes' => [
                'cipav'          => $frozen->cipav          ?? [],
                'carpimko'       => $frozen->carpimko       ?? [],
                'regimes_points' => $frozen->regimes_points ?? [],
            ],

            'scenarios_retenus' => $scenariosRetenus,
            'dates_retenues'    => $datesRetenues,
            'calculs_skills'    => $calculsSkills,
            'alertes'           => $frozen->alertes ?? [],

            // Bloc legacy : conservé tant que le workflow n8n n'a pas migré
            // vers la lecture des champs v2 racine. À supprimer une fois le
            // workflow PREP PAYLOAD mis à jour pour consommer la v2.
            'frozen_data' => [
                'user_id'  => $clientId,
                'meta'     => $resolvedMeta,
                'totaux'   => $totaux,
                'carriere' => $carriere,
                'cipav'    => $frozen->cipav   ?? [],
                'alertes'  => $frozen->alertes ?? [],
                'locked'   => $frozen->isLocked(),
            ],
        ];
    }

    /**
     * Calcule SAM (moyenne des 25 meilleurs salaires revalorisés) et
     * trimestres_cotises_rg (CNAV) à partir de la carrière.
     */
    private static function enrichTotaux(array $totaux, array $carriere): array
    {
        if (! empty($carriere)) {
            $revalos = array_column($carriere, 'salaire_revalo');
            rsort($revalos);
            $top25 = array_slice($revalos, 0, 25);
            $sam = count($top25) ? (int) round(array_sum($top25) / count($top25)) : 0;
            if ($sam > 0) {
                $totaux['sam'] = $sam;
            }
        }

        $parRegime = $totaux['trimestres_par_regime'] ?? [];
        $totaux['trimestres_cotises_rg'] = $parRegime['cnav']
            ?? $totaux['trimestres_cnav']
            ?? $totaux['trimestres_regime_general']
            ?? $totaux['trimestres_cotises']
            ?? 0;

        return $totaux;
    }

    /**
     * Normalise un champ tableau avec fallback sur l'ancien champ singulier.
     * Renvoie toujours un tableau (vide si rien de défini).
     */
    private static function normalizeArrayWithFallback($pluralValue, $singularValue): array
    {
        if (is_array($pluralValue) && ! empty($pluralValue)) {
            return $pluralValue;
        }
        if (is_array($singularValue) && ! empty($singularValue)) {
            return [$singularValue];
        }
        return [];
    }

    /**
     * Complète meta.date_naissance depuis personal_informations si manquant.
     */
    private static function resolveMeta(array $meta, int $clientId): array
    {
        if (empty($meta['date_naissance'])) {
            $rawDate = DB::table('personal_informations')
                ->where('user_id', $clientId)
                ->value('birth_date');
            if ($rawDate) {
                $meta['date_naissance'] = $rawDate;
            }
        }
        return $meta;
    }

    /**
     * POST /api/v1/simulation-retraite-store
     * Appelé par n8n pour stocker le rapport (conservé pour compatibilité).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id'   => 'required|integer',
            'html_report' => 'required|string',
            'calcul_json' => 'nullable|array',
        ]);

        $clientId = (int) $validated['client_id'];

        $report = AnalysisReport::updateOrCreate(
            [
                'user_id'  => $clientId,
                'skill_id' => self::SKILL_ID,
                'statut'   => 'brouillon',
            ],
            [
                'result_json'         => $validated['html_report'],
                'calcul_json'         => $validated['calcul_json'] ?? [],
                'restitution_json'    => [],
                'alertes_json'        => [],
                'arret_critique_json' => [],
                'statut'              => 'brouillon',
            ]
        );

        return response()->json([
            'success'   => true,
            'report_id' => $report->id,
            'client_id' => $clientId,
        ], 201);
    }

    /**
     * DELETE /api/v1/simulation-retraite/{clientId}
     * Supprime tous les rapports de simulation d'un client.
     */
    public function destroy(int $clientId): JsonResponse
    {
        $deleted = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', self::SKILL_ID)
            ->delete();

        return response()->json([
            'success'   => true,
            'deleted'   => $deleted,
            'client_id' => $clientId,
        ]);
    }

    /**
     * GET /api/v1/simulation-retraite/{clientId}
     * Retourne le dernier rapport de simulation pour un client.
     */
    public function getByClient(int $clientId): JsonResponse
    {
        $report = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', self::SKILL_ID)
            ->orderByDesc('created_at')
            ->first();

        if (! $report) {
            return response()->json([
                'error'     => 'No simulation report found',
                'client_id' => $clientId,
            ], 404);
        }

        return response()->json([
            'id'          => $report->id,
            'client_id'   => $clientId,
            'html_report' => $report->result_json,
            'calcul_json' => $report->calcul_json,
            'statut'      => $report->statut,
            'created_at'  => $report->created_at,
        ]);
    }

    /**
     * PATCH /api/v1/simulation-retraite/{clientId}/html
     * Met à jour le HTML du dernier rapport de simulation
     * (édition consultant via ReportViewerModal — EOR-61).
     */
    public function updateHtml(Request $request, int $clientId): JsonResponse
    {
        $validated = $request->validate([
            'html_report' => 'required|string',
        ]);

        $report = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', self::SKILL_ID)
            ->orderByDesc('created_at')
            ->first();

        if (! $report) {
            return response()->json([
                'error'     => 'No simulation report found',
                'client_id' => $clientId,
            ], 404);
        }

        $report->result_json = $validated['html_report'];
        $report->save();

        return response()->json([
            'success'   => true,
            'report_id' => $report->id,
            'client_id' => $clientId,
        ]);
    }
}
