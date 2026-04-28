<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\FrozenData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SimulationRetraiteController extends Controller
{
    private const SKILL_ID   = 'simulation_retraite';
    private const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/simulation-retraite';

    /**
     * POST /api/v1/simulation-retraite/generate
     *
     * Appelé par le frontend. Charge frozen_data depuis la DB,
     * forward à n8n, et retourne le rapport HTML directement.
     * n8n n'a donc jamais besoin de rappeler l'API.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        $clientId = (int) $request->input('client_id');

        // Charger frozen_data depuis la base de données
        $frozenData = FrozenData::where('user_id', $clientId)->latest()->first();

        if (! $frozenData) {
            return response()->json([
                'error' => 'Données carrière introuvables pour ce client. Veuillez d\'abord valider la carrière.',
            ], 404);
        }

        $payload = [
            'client_id'     => $clientId,
            'revenu_souhaite' => (float) $request->input('revenu_souhaite', 0),
            'frozen_data'   => [
                'user_id'  => $clientId,
                'meta'     => $frozenData->meta     ?? [],
                'totaux'   => $frozenData->totaux   ?? [],
                'carriere' => $frozenData->carriere ?? [],
                'cipav'    => $frozenData->cipav    ?? [],
                'alertes'  => $frozenData->alertes  ?? [],
                'locked'   => $frozenData->isLocked(),
            ],
        ];

        try {
            $n8nResponse = Http::timeout(900)->post(self::N8N_WEBHOOK, $payload);

            $body = $n8nResponse->json() ?? [];

            // Stocker le rapport en base si n8n a renvoyé du HTML
            if (! empty($body['html_report'])) {
                AnalysisReport::updateOrCreate(
                    [
                        'user_id'  => $clientId,
                        'skill_id' => self::SKILL_ID,
                        'statut'   => 'brouillon',
                    ],
                    [
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
                'success'    => $n8nResponse->successful(),
                'html_report' => $body['html_report'] ?? null,
                'client_id'  => $clientId,
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
}
