<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use Illuminate\Http\Request;

class AnalysisReportController extends Controller
{
    /**
     * GET /api/v1/analysis-reports
     * Liste avec filtres : client_id (alias user_id), skill_id, statut
     */
    public function index(Request $request)
    {
        $q = AnalysisReport::query()->orderByDesc('id');

        // client_id et user_id sont équivalents
        $clientId = $request->filled('client_id') ? $request->query('client_id') : $request->query('user_id');
        if ($clientId) {
            $q->where('user_id', (int) $clientId);
        }

        if ($request->filled('skill_id')) {
            $q->where('skill_id', $request->query('skill_id'));
        }

        if ($request->filled('statut')) {
            $q->where('statut', $request->query('statut'));
        }

        return response()->json(
            $q->paginate((int) $request->query('per_page', 50))
        );
    }

    /**
     * GET /api/v1/analysis-reports/latest/{clientId}/{skillCode}
     * Dernier rapport d'un client pour un skill donné (utilisé par n8n / Raph)
     */
    public function latest(int $clientId, string $skillCode)
    {
        $report = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', strtolower($skillCode))
            ->orderByDesc('created_at')
            ->first();

        if (!$report) {
            return response()->json([
                'error'      => 'No report found',
                'client_id'  => $clientId,
                'skill_code' => $skillCode,
            ], 404);
        }

        return response()->json($report);
    }

    /**
     * GET /api/v1/analysis-reports/{id}
     */
    public function show(AnalysisReport $analysisReport)
    {
        return response()->json($analysisReport);
    }

    /**
     * GET /api/v1/analysis-reports/client/{clientId}
     * Tous les rapports d'un client
     */
    public function getByClient(int $clientId)
    {
        $reports = AnalysisReport::where('user_id', $clientId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($reports);
    }

    /**
     * POST /api/v1/analysis-reports
     * Création par n8n (callback après analyse)
     */
    public function store(Request $request)
    {
        // Supporte format n8n : [ { ... } ]
        $payload = $request->all();
        if (is_array($payload) && array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            $payload = $payload[0];
        }

        $data = validator($payload, [
            'user_id'              => ['required', 'integer', 'exists:users,id'],
            'frozen_data_id'       => ['nullable', 'integer'],
            'skill_id'             => ['required', 'string', 'max:64'],
            'result_json'          => ['required', 'array'],
            'calcul_json'          => ['nullable', 'array'],
            'restitution_json'     => ['nullable', 'array'],
            'alertes_json'         => ['nullable', 'array'],
            'arret_critique_json'  => ['nullable', 'array'],
            'statut'               => ['sometimes', 'in:brouillon,valide,livre'],
        ])->validate();

        $report = AnalysisReport::create($data);

        return response()->json($report, 201);
    }

    /**
     * PUT /api/v1/analysis-reports/{id}
     * Mise à jour (ex: ajout du calcul_json après retour Python, ou du restitution_json)
     */
    public function update(Request $request, AnalysisReport $analysisReport)
    {
        $data = validator($request->all(), [
            'frozen_data_id'       => ['sometimes', 'nullable', 'integer'],
            'result_json'          => ['sometimes', 'array'],
            'calcul_json'          => ['sometimes', 'nullable', 'array'],
            'restitution_json'     => ['sometimes', 'nullable', 'array'],
            'alertes_json'         => ['sometimes', 'nullable', 'array'],
            'arret_critique_json'  => ['sometimes', 'nullable', 'array'],
            'statut'               => ['sometimes', 'in:brouillon,valide,livre'],
        ])->validate();

        $analysisReport->fill($data)->save();

        return response()->json($analysisReport);
    }

    /**
     * POST /api/v1/analysis-reports/{id}/validate
     * Le consultant valide le rapport
     */
    public function validateReport(Request $request, AnalysisReport $analysisReport)
    {
        $auth = auth()->user();

        // Gate #2 : un rapport avec arrêt critique ne peut pas être validé/livré.
        if (! $analysisReport->canBeDelivered()) {
            return response()->json([
                'error'          => "Livraison bloquée : ce rapport contient un arrêt critique (règle Gate #2). Corrigez l'incohérence avant de valider.",
                'arret_critique' => $analysisReport->arret_critique_json,
            ], 422);
        }

        $analysisReport->update([
            'statut'       => 'valide',
            'validated_by' => $auth->id,
            'validated_at' => now(),
        ]);

        return response()->json($analysisReport);
    }

    /**
     * DELETE /api/v1/analysis-reports/{id}
     */
    public function destroy(AnalysisReport $analysisReport)
    {
        $analysisReport->delete();

        return response()->json(null, 204);
    }
}
