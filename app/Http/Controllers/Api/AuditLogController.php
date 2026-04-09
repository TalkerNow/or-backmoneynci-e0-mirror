<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\AuditLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private AuditLogRepository $repository)
    {
    }

    /**
     * GET /api/audit_log?client_id=X ou ?user_id=X
     * Historique paginé — pour afficher les runs passés d'un client ou d'un consultant.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('client_id')) {
            $logs = $this->repository->getByClientId((int) $request->client_id);
        } elseif ($request->filled('user_id')) {
            $logs = $this->repository->getByUserId((int) $request->user_id);
        } else {
            return response()->json(['message' => 'Paramètre client_id ou user_id requis.'], 422);
        }

        return response()->json($logs);
    }

    /**
     * GET /api/audit_log/{id}
     * Détail d'une entrée — pour rejouer ou auditer un calcul précis.
     */
    public function show(int $id): JsonResponse
    {
        $log = $this->repository->getById($id);

        if (!$log) {
            return response()->json(['message' => 'Entrée introuvable.'], 404);
        }

        return response()->json($log);
    }

    /**
     * POST /api/audit_log
     * Crée une entrée d'audit. Appelé par le skill controller Laravel ou par n8n.
     *
     * Body attendu :
     * {
     *   "user_id": 1,
     *   "client_id": 42,
     *   "frozen_data_id": 7,          // optionnel
     *   "skill_name": "audit-carriere-longue",
     *   "skill_version": "1.0.0",     // optionnel
     *   "system_prompt_id": 3,        // optionnel
     *   "model_used": "gemini-2.5-flash-preview-09-2025",
     *   "llm_request": {...},         // prompt complet envoyé
     *   "llm_response_raw": {...},    // réponse brute LLM
     *   "tokens_input": 1200,
     *   "tokens_output": 400,
     *   "python_request": {...},      // optionnel
     *   "python_response_raw": {...}, // optionnel
     *   "user_context": "...",        // optionnel
     *   "status": "success",          // 'success' | 'error' | 'retry'
     *   "error_message": null,
     *   "latency_ms": 3200
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'            => 'required|integer',
            'client_id'          => 'required|integer',
            'frozen_data_id'     => 'nullable|integer',
            'skill_name'         => 'required|string|max:100',
            'skill_version'      => 'nullable|string|max:20',
            'system_prompt_id'   => 'nullable|integer',
            'model_used'         => 'required|string|max:100',
            'llm_request'        => 'nullable|array',
            'llm_response_raw'   => 'nullable|array',
            'tokens_input'       => 'nullable|integer',
            'tokens_output'      => 'nullable|integer',
            'python_request'     => 'nullable|array',
            'python_response_raw' => 'nullable|array',
            'user_context'       => 'nullable|string',
            'status'             => 'required|in:success,error,retry',
            'error_message'      => 'nullable|string',
            'latency_ms'         => 'nullable|integer',
        ]);

        $log = $this->repository->create($data);

        return response()->json($log, 201);
    }
}
