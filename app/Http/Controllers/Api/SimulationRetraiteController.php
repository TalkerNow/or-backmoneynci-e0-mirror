<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulationRetraiteController extends Controller
{
    private const SKILL_ID = 'simulation_retraite';

    /**
     * POST /api/v1/simulation-retraite-store
     * Called by n8n after generating the HTML report.
     * Upserts into analysis_reports (one active report per client).
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
                'result_json'          => $validated['html_report'],
                'calcul_json'          => $validated['calcul_json'] ?? [],
                'restitution_json'     => [],
                'alertes_json'         => [],
                'arret_critique_json'  => [],
                'statut'               => 'brouillon',
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
     * Returns the latest simulation report for a client.
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
