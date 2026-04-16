<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScriptCalculateController extends Controller
{
    private const WEBHOOKS = [
        'CNAV'        => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cnav-v2-test',
        'AGIRC_ARRCO' => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-agirc-arrco-v2-test',
        'IRCANTEC'    => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-ircantec-v2-test',
        'RCI'         => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-rci-v2-test',
        'CIPAV'       => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cipav-v2-test',
    ];

    /**
     * POST /api/script/calculate
     *
     * Proxy multi-régimes vers n8n — évite CORS depuis le navigateur.
     *
     * Body attendu :
     * {
     *   "regime_code":     "CNAV" | "AGIRC_ARRCO" | "IRCANTEC" | "RCI" | "CIPAV",
     *   "client_id":       42,
     *   "token":           "...",
     *   "user_context":    "",
     *   "scenario_params": {},
     *   "frozen_data_id":  null
     * }
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'regime_code'     => 'required|string',
            'client_id'       => 'required|integer',
            'token'           => 'required|string',
            'user_context'    => 'nullable|string',
            'scenario_params' => 'nullable|array',
            'frozen_data_id'  => 'nullable|integer',
        ]);

        $regimeCode = strtoupper($data['regime_code']);

        if (!isset(self::WEBHOOKS[$regimeCode])) {
            return response()->json(['message' => "Régime inconnu : {$regimeCode}"], 400);
        }

        $payload = array_merge($data, ['regime_code' => $regimeCode]);

        $n8nResponse = Http::timeout(120)->post(self::WEBHOOKS[$regimeCode], $payload);

        if (!$n8nResponse->successful()) {
            return response()->json([
                'message' => 'Le webhook a retourné une erreur.',
                'status'  => $n8nResponse->status(),
            ], 502);
        }

        $result = $n8nResponse->json();
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        return response()->json($result);
    }
}
