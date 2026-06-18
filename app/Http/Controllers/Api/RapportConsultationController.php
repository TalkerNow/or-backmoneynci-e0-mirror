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
    // Webhook "léger" (PREP -> /simulate -> calcul, SANS Gemini) : projette les trimestres PAR
    // DATE depuis la carrière gelée. Source unique du calcul par date = moteur Python /simulate.
    const SIMULATE_MULTIDATE_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/simulate-multidate-calc';

    // Timeout du POST vers n8n. Doit rester < (php max_execution_time, nginx fastcgi_read_timeout)
    // sinon la requête est tuée AVANT que Guzzle ne lâche prise et on perd la réponse n8n.
    // Cf docker/php/custom.ini (max_execution_time=360) et docker/nginx/prod.conf (fastcgi_read_timeout 360).
    const N8N_TIMEOUT_SECONDS = 340;

    /**
     * Projection PAR DATE recalculée depuis la carrière GELÉE via le moteur Python /simulate
     * (webhook léger sans Gemini). Retourne le tableau `par_date` au format attendu par
     * AGREGATION FINALE.buildProjectionFromPython, ou null si indisponible. Jamais bloquant.
     */
    private function computeFreshParDate(int $clientId, FrozenData $frozenData): ?array
    {
        try {
            $dates = $frozenData->dates_retenues;
            if (is_string($dates)) {
                $dates = json_decode($dates, true);
            }
            if (!is_array($dates) || count($dates) === 0) {
                return null;
            }

            // Scénarios retenus (dont le rachat VPLR avec params.dates_cibles) : sans eux le moteur
            // ignore le VPLR et la consultation n'affiche jamais l'effet du rachat. Même normalisation
            // que SimulationRetraiteController (pluriel scenarios_choisis, fallback singulier scenario_choisi).
            $scenariosRetenus = $frozenData->scenarios_choisis;
            if (is_string($scenariosRetenus)) {
                $scenariosRetenus = json_decode($scenariosRetenus, true);
            }
            if (!is_array($scenariosRetenus) || count($scenariosRetenus) === 0) {
                $single = $frozenData->scenario_choisi;
                if (is_string($single)) {
                    $single = json_decode($single, true);
                }
                $scenariosRetenus = (is_array($single) && count($single) > 0) ? [$single] : [];
            }

            $payload = [
                'client_id'         => $clientId,
                'carriere'          => $frozenData->carriere,
                'totaux'            => $frozenData->totaux,
                'dates_retenues'    => array_map(fn ($d) => [
                    'date'  => $d['date']  ?? null,
                    'type'  => $d['type']  ?? null,
                    'label' => $d['label'] ?? null,
                ], array_values($dates)),
                'scenarios_retenus' => $scenariosRetenus,
                'regimes'           => [
                    'cipav'          => $frozenData->cipav,
                    'carpimko'       => $frozenData->carpimko,
                    'regimes_points' => $frozenData->regimes_points,
                ],
                'frozen_data'       => [
                    'meta'           => $frozenData->meta,
                    'totaux'         => $frozenData->totaux,
                    'carriere'       => $frozenData->carriere,
                    'cipav'          => $frozenData->cipav,
                    'carpimko'       => $frozenData->carpimko,
                    'regimes_points' => $frozenData->regimes_points,
                    'user_id'        => $clientId,
                ],
            ];

            $resp = Http::timeout(120)->post(self::SIMULATE_MULTIDATE_WEBHOOK, $payload);
            if (!$resp->successful()) {
                Log::warning('computeFreshParDate: /simulate webhook a échoué', ['status' => $resp->status()]);
                return null;
            }
            $scenarios = $resp->json('scenarios') ?? [];
            if (!is_array($scenarios) || count($scenarios) === 0) {
                return null;
            }

            // Index des dates choisies pour récupérer le label par date_depart.
            $byDate = [];
            foreach ($dates as $d) {
                if (!empty($d['date'])) {
                    $byDate[(string) $d['date']] = $d;
                }
            }

            // Mapping scénario /simulate -> format par_date (identique au front runMultiDateScenarios).
            $parDate = [];
            foreach ($scenarios as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $dd  = $s['date_depart'] ?? null;
                $src = $byDate[(string) $dd] ?? [];
                $parDate[] = [
                    'date_depart'     => $dd,
                    'label'           => $src['label'] ?? null,
                    'age_depart_mois' => (int) (($s['age_depart_annees'] ?? 0) * 12) + (int) ($s['age_depart_mois'] ?? 0),
                    'regimes'         => [
                        'CNAV' => [
                            'trimestres_valides_tous_regimes' => $s['trimestres_acquis_tous']  ?? null,
                            'trimestres_cotises_rg'           => $s['trimestres_rg_at_depart'] ?? null,
                            'taux_liquidation'                => isset($s['taux_cnav']) ? $s['taux_cnav'] / 100 : null,
                            'pension_mensuelle_brute'         => $s['pension_cnav_mensuelle']  ?? null,
                        ],
                        'AGIRC_ARRCO' => ['pension_mensuelle_brute' => $s['pension_agirc_mensuelle']    ?? null],
                        'IRCANTEC'    => ['pension_mensuelle_brute' => $s['pension_ircantec_mensuelle'] ?? null],
                        'RCI'         => ['pension_mensuelle_brute' => $s['pension_rci_mensuelle']      ?? null],
                        'CIPAV'       => ['pension_mensuelle_brute' => ($s['pension_cipav_base_mensuelle'] ?? 0) + ($s['pension_cipav_compl_mensuelle'] ?? 0)],
                    ],
                    'total_mensuel_brut'   => $s['pension_totale_brute'] ?? null,
                    'pension_totale_nette' => $s['pension_totale_nette'] ?? null,
                    // Rachat VPLR appliqué à CETTE date (null/utile=false si non ciblée) + trimestres
                    // bruts (avant rachat) pour permettre l'annotation explicite côté rendu n8n.
                    'vplr_applique'        => $s['vplr_rachat_applique'] ?? null,
                    'trimestres_tous_brut' => $s['trimestres_tous_brut'] ?? null,
                    'trimestres_rg_brut'   => $s['trimestres_rg_brut'] ?? null,
                    'regimes_en_erreur'    => [],
                ];
            }
            if (count($parDate) === 0) {
                return null;
            }
            usort($parDate, fn ($a, $b) => strcmp((string) $a['date_depart'], (string) $b['date_depart']));

            return $parDate;
        } catch (\Throwable $e) {
            Log::warning('computeFreshParDate: exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

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

        // FIX "trimestres identiques sur toutes les dates" : le tableau multi-dates de la
        // consultation (AGREGATION FINALE -> buildProjectionFromPython) lit le rapport_dates
        // produit par le FRONT, souvent périmé (bundle non recompilé) ou figé au total gelé.
        // On recalcule ici la projection PAR DATE depuis la carrière GELÉE via /simulate (le
        // même moteur Python que le livrable simulation) et on l'injecte, en remplaçant tout
        // rapport_dates périmé. Tolérant : si /simulate échoue, on garde l'existant.
        if ($frozenData) {
            $freshParDate = $this->computeFreshParDate($clientId, $frozenData);
            if (is_array($freshParDate) && count($freshParDate) > 0) {
                $calculs = array_values(array_filter(
                    $simulateurContext['calculs'] ?? [],
                    fn ($c) => strtolower($c['skill_id'] ?? '') !== 'rapport_dates'
                ));
                $calculs[] = [
                    'skill_id'            => 'rapport_dates',
                    'result_json'         => [
                        'par_date'       => $freshParDate,
                        'mode'           => 'simulate_engine_v2_backend',
                        'frozen_data_id' => $frozenData->id,
                    ],
                    'calcul_json'         => null,
                    'alertes_json'        => null,
                    'arret_critique_json' => null,
                    'updated_at'          => now()->toIso8601String(),
                ];
                $simulateurContext['calculs'] = $calculs;
                Log::info('rapport_consultation: par_date rafraîchi via /simulate', [
                    'client_id' => $clientId,
                    'nb_dates'  => count($freshParDate),
                    'trims'     => array_map(fn ($p) => $p['regimes']['CNAV']['trimestres_valides_tous_regimes'] ?? null, $freshParDate),
                ]);
            }
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

            // Override possible via .env (RAPPORT_CONSULTATION_WEBHOOK) pour router vers une COPIE
            // du workflow n8n (test), sans toucher le live. Défaut = const = workflow de prod.
            $consultationWebhook = env('RAPPORT_CONSULTATION_WEBHOOK') ?: self::N8N_WEBHOOK;
            $n8nResponse = $n8nRequest->post($consultationWebhook);
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

            // Enforcement Gate #2 — DÉTERMINISTE côté Laravel : on évalue les règles
            // ACTIVES du registre éditable (source unique de vérité) contre un namespace
            // construit depuis frozen_data (meta + totaux). Une règle CRITIQUE déclenchée
            // => arrêt critique => livraison bloquée (garde sur AnalysisReport).
            $enforcement = (new \App\Services\Registre\RuleEvaluator())->evaluate(
                (new \App\Services\Registre\RegistreRules())->selectActiveRules(),
                \App\Services\Registre\NamespaceBuilder::fromFrozenData($frozenData?->meta, $frozenData?->totaux)
            );

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
                    'alertes_json'        => $enforcement['alertes'],
                    'arret_critique_json' => $enforcement['arret_critique'] ?? [],
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
                'success'        => true,
                'report_id'      => $report->id,
                'n8n_status'     => $n8nResponse->status(),
                'elapsed_s'      => $elapsed,
                'alertes'        => $enforcement['alertes'],
                'arret_critique' => $enforcement['arret_critique'],
                'data'           => $n8nResponse->json() ?? $n8nResponse->body(),
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
