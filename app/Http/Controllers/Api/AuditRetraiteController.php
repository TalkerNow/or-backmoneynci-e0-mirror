<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\FrozenData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuditRetraiteController extends Controller
{
    const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/audit-retraite';

    /**
     * POST /api/v1/audit-retraite/generate
     *
     * Reçoit le contexte simulateur (JSON) depuis le frontend,
     * enrichit avec frozen_data + analysis_reports DB, forward à n8n.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        $clientId = (int) $request->input('client_id');

        $payload = [
            'client_id'           => $clientId,
            'dispositifs_actives' => $request->input('dispositifs_actives', []),
            'profil_client'       => $request->input('profil_client', []),
            'regimes'             => $request->input('regimes', []),
            'dates_simulees'      => $request->input('dates_simulees', []),
            'simulation_context'  => $request->input('simulation_context', []),
        ];

        // Enrichir avec frozen_data (carrière validée)
        $frozenData = FrozenData::where('user_id', $clientId)->latest()->first();
        if ($frozenData) {
            $payload['frozen_data'] = [
                'carriere' => $frozenData->carriere,
                'cipav'    => $frozenData->cipav,
                'totaux'   => $frozenData->totaux,
                'alertes'  => $frozenData->alertes,
                'locked'   => $frozenData->isLocked(),
            ];
        }

        // Enrichir avec les calculs régimes existants en base
        $analysisReports = AnalysisReport::where('user_id', $clientId)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('skill_id');

        if ($analysisReports->isNotEmpty()) {
            $payload['calculs_existants'] = $analysisReports->map(fn($r) => [
                'skill_id'    => $r->skill_id,
                'result_json' => $r->result_json,
                'updated_at'  => $r->updated_at?->toIso8601String(),
            ])->values()->toArray();
        }

        try {
            $n8nResponse = Http::timeout(360)->post(self::N8N_WEBHOOK, $payload);

            return response()->json([
                'success'    => $n8nResponse->successful(),
                'n8n_status' => $n8nResponse->status(),
                'data'       => $n8nResponse->json() ?? $n8nResponse->body(),
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/audit-retraite/store
     *
     * Appelé par n8n après génération HTML — stocke le résultat.
     * Sans auth JWT : n8n appelle cet endpoint directement.
     */
    public function store(Request $request): JsonResponse
    {
        $clientId    = (int) $request->input('client_id');
        $htmlContent = $request->input('text', '');

        $report = AnalysisReport::create([
            'user_id'     => $clientId,
            'skill_id'    => 'audit_retraite',
            'result_json' => [
                'htmlContent'         => $htmlContent,
                'profil_client'       => $request->input('profil_client', []),
                'regimes'             => $request->input('regimes', []),
                'dispositifs_actives' => $request->input('dispositifs_actives', []),
                'dates_simulees'      => $request->input('dates_simulees', []),
            ],
            'statut' => 'brouillon',
        ]);

        return response()->json($report, 201);
    }
}
