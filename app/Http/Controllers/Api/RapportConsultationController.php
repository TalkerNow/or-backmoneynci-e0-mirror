<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\DepartureRule;
use App\Models\FrozenData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RapportConsultationController extends Controller
{
    const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/f012dfc7-8b2c-479f-af1f-20dcd44cda02';
    const SKILL_ID    = 'rapport_consultation';

    // Timeout du POST vers n8n. Doit rester < (php max_execution_time, nginx fastcgi_read_timeout)
    // sinon la requête est tuée AVANT que Guzzle ne lâche prise et on perd la réponse n8n.
    // Cf docker/php/custom.ini (max_execution_time=360) et docker/nginx/prod.conf (fastcgi_read_timeout 360).
    const N8N_TIMEOUT_SECONDS = 340;

    /**
     * POST /api/v1/rapports/consultation
     *
     * Reçoit le PDF RIS + contexte client depuis le frontend,
     * enrichit avec frozen_data + analysis_reports du simulateur,
     * forward à n8n en multipart. CDC-compliant : frontend → backend → n8n.
     */
    public function generate(Request $request): JsonResponse
    {
        // PHP runtime ceiling — sans ça, max_execution_time peut couper avant
        // que la réponse n8n ne soit complètement reçue/sérialisée.
        @set_time_limit(0);

        $request->validate([
            'file'      => 'nullable|file',
            'client_id' => 'required',
        ]);

        $file     = $request->file('file');
        $message  = $request->input('message', '');
        $clientId = (int) $request->input('client_id');
        $startedAt = microtime(true);

        // Charger les données simulateur depuis la DB
        $frozenData      = FrozenData::where('user_id', $clientId)->latest()->first();
        $analysisReports = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', '!=', self::SKILL_ID)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Construire le contexte simulateur à injecter dans n8n
        $simulateurContext = [];

        if ($frozenData) {
            $simulateurContext['frozen_data'] = [
                'carriere'          => $frozenData->carriere,
                'cipav'             => $frozenData->cipav,
                'carpimko'          => $frozenData->carpimko,
                'regimes_points'    => $frozenData->regimes_points,
                'totaux'            => $frozenData->totaux,
                'alertes'           => $frozenData->alertes,
                'meta'              => $frozenData->meta,
                'locked'            => $frozenData->isLocked(),
                'scenarios_choisis' => $frozenData->scenarios_choisis,
                'dates_retenues'    => $frozenData->dates_retenues,
            ];
        }

        if ($analysisReports->isNotEmpty()) {
            $simulateurContext['calculs'] = $analysisReports->map(fn($r) => [
                'skill_id'            => $r->skill_id,
                'result_json'         => $r->result_json,
                'calcul_json'         => $r->calcul_json,
                'alertes_json'        => $r->alertes_json,
                'arret_critique_json' => $r->arret_critique_json,
                'updated_at'          => $r->updated_at?->toIso8601String(),
            ])->values()->toArray();
        }

        Log::info('rapport_consultation: start', [
            'client_id'      => $clientId,
            'file_name'      => $file?->getClientOriginalName(),
            'file_size'      => $file?->getSize(),
            'has_file'       => $file !== null,
            'frozen_data_id' => $frozenData?->id,
            'calculs_count'  => $analysisReports->count(),
        ]);

        try {
            $n8nRequest = Http::timeout(self::N8N_TIMEOUT_SECONDS);

            if ($file !== null) {
                $n8nRequest = $n8nRequest->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName());
            }

            if ($message !== '') {
                $n8nRequest = $n8nRequest->attach('message', $message, null);
            }
            $n8nRequest = $n8nRequest->attach('client_id', (string) $clientId, null);

            if ($request->input('system_prompt')) {
                $n8nRequest = $n8nRequest->attach('system_prompt', $request->input('system_prompt'), null);
            }
            if ($request->input('user_context')) {
                $n8nRequest = $n8nRequest->attach('user_context', $request->input('user_context'), null);
            }

            if ($request->input('user_context')) {
                $n8nRequest = $n8nRequest->attach('user_context', $request->input('user_context'), null);
            }

            // Injecter les données simulateur si disponibles
            if (!empty($simulateurContext)) {
                $n8nRequest = $n8nRequest->attach(
                    'simulateur_context',
                    json_encode($simulateurContext, JSON_UNESCAPED_UNICODE),
                    null
                );
            }

            // Barème de départ (EOR-80) — injecté pour que AGREGATION FINALE puisse lire les valeurs DB
            $baremeDepart = DepartureRule::ordered()->get()->map(fn ($r) => [
                'key_max'    => $r->key_max,
                'age_months' => $r->age_months,
                'trim'       => $r->trim,
                'is_default' => (bool) $r->is_default,
            ])->toArray();
            $n8nRequest = $n8nRequest->attach(
                'bareme_depart',
                json_encode($baremeDepart, JSON_UNESCAPED_UNICODE),
                null
            );

            // Circulaires : routage DÉTERMINISTE (zéro Gemini côté Laravel). On embarque
            // les agents sélectionnés (prompt + corps de circulaire) en multipart. n8n lit
            // body.circulaires, appelle Gemini par agent (node AGENT CIRCULAIRE) et ajoute
            // la section "Cadre réglementaire" au rapport. Tolérant aux erreurs.
            try {
                $circulaires = app(\App\Services\Circulaires\Selector::class)
                    ->selectAgents($simulateurContext, $request->input('user_context'));
                if (!empty($circulaires)) {
                    $n8nRequest = $n8nRequest->attach(
                        'circulaires',
                        json_encode($circulaires, JSON_UNESCAPED_UNICODE),
                        null
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('rapport_consultation: circulaires routing skipped', ['err' => $e->getMessage()]);
            }

            $n8nResponse = $n8nRequest->post(self::N8N_WEBHOOK);
            $elapsed = round(microtime(true) - $startedAt, 1);

            // Cas 1 : n8n a renvoyé un code d'erreur HTTP
            if (! $n8nResponse->successful()) {
                Log::error('rapport_consultation: n8n returned error status', [
                    'client_id'   => $clientId,
                    'status'      => $n8nResponse->status(),
                    'body_excerpt' => mb_substr((string) $n8nResponse->body(), 0, 500),
                    'elapsed_s'   => $elapsed,
                ]);

                return response()->json([
                    'success'    => false,
                    'error'      => "Le workflow n8n a renvoyé une erreur ({$n8nResponse->status()}). Réessayez ou vérifiez les logs n8n.",
                    'n8n_status' => $n8nResponse->status(),
                    'elapsed_s'  => $elapsed,
                ], 502);
            }

            // Cas 2 : HTTP 200 mais réponse vide ou sans HTML exploitable
            $rawHtml = $this->extractHtml($n8nResponse->json() ?? $n8nResponse->body());
            if ($rawHtml === null || $rawHtml === '') {
                Log::error('rapport_consultation: n8n returned 200 but no extractable HTML', [
                    'client_id'   => $clientId,
                    'body_excerpt' => mb_substr((string) $n8nResponse->body(), 0, 500),
                    'elapsed_s'   => $elapsed,
                ]);

                return response()->json([
                    'success'   => false,
                    'error'     => "n8n a répondu mais sans contenu HTML exploitable. Le workflow a probablement échoué côté IA — réessayez.",
                    'elapsed_s' => $elapsed,
                ], 502);
            }

            // Cas 3 : succès — persistance immédiate côté backend (EOR-61).
            // updateOrCreate sur (user_id, skill_id, statut=brouillon) pour éviter
            // les doublons quand le consultant régénère plusieurs fois.
            $report = AnalysisReport::updateOrCreate(
                [
                    'user_id'  => $clientId,
                    'skill_id' => self::SKILL_ID,
                    'statut'   => 'brouillon',
                ],
                [
                    'frozen_data_id' => $frozenData?->id,
                    'result_json'    => [
                        'id'          => 'rc_backend_' . time(),
                        'name'        => 'Rapport de consultation retraite',
                        'type'        => 'rapport_consultation',
                        'createdAt'   => now()->toIso8601String(),
                        'url'         => null,
                        'htmlContent' => $rawHtml,
                    ],
                    'statut' => 'brouillon',
                ]
            );

            Log::info('rapport_consultation: success', [
                'client_id'  => $clientId,
                'report_id'  => $report->id,
                'html_bytes' => strlen($rawHtml),
                'elapsed_s'  => $elapsed,
            ]);

            return response()->json([
                'success'    => true,
                'report_id'  => $report->id,
                'n8n_status' => $n8nResponse->status(),
                'elapsed_s'  => $elapsed,
                'data'       => $n8nResponse->json() ?? $n8nResponse->body(),
            ], 200);
        } catch (ConnectionException $e) {
            $elapsed = round(microtime(true) - $startedAt, 1);
            Log::error('rapport_consultation: connection/timeout to n8n', [
                'client_id' => $clientId,
                'message'   => $e->getMessage(),
                'elapsed_s' => $elapsed,
            ]);
            return response()->json([
                'success'   => false,
                'error'     => "Timeout en attendant n8n (>{$elapsed}s). Le workflow IA est probablement encore en cours — réessayez dans une minute.",
                'elapsed_s' => $elapsed,
            ], 504);
        } catch (\Throwable $e) {
            $elapsed = round(microtime(true) - $startedAt, 1);
            Log::error('rapport_consultation: unexpected exception', [
                'client_id' => $clientId,
                'message'   => $e->getMessage(),
                'class'     => get_class($e),
                'elapsed_s' => $elapsed,
            ]);
            return response()->json([
                'success'   => false,
                'error'     => 'Erreur serveur inattendue : ' . $e->getMessage(),
                'elapsed_s' => $elapsed,
            ], 500);
        }
    }

    /**
     * Extrait le HTML d'une réponse n8n hétérogène
     * (string, tableau, objet avec html_report/output/text/response).
     */
    private function extractHtml($data): ?string
    {
        if ($data === null) return null;
        $root = is_array($data) && array_keys($data) === range(0, count($data) - 1) ? ($data[0] ?? null) : $data;
        if (is_string($root)) return $root !== '' ? $root : null;
        if (is_array($root)) {
            foreach (['html_report', 'output', 'text', 'response'] as $key) {
                if (!empty($root[$key]) && is_string($root[$key])) return $root[$key];
            }
        }
        return null;
    }
}
