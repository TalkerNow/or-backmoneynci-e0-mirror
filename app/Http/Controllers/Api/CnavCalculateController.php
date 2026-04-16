<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CnavCalculateController extends Controller
{
    private const WEBHOOK_URL = 'https://n8n.srv796541.hstgr.cloud/webhook/skill-execute-cnav-v2-1-test';

    /**
     * POST /api/cnav/calculate
     *
     * Proxies the CNAV calculation request to the n8n webhook,
     * bypassing browser CORS restrictions.
     *
     * Body attendu :
     * {
     *   "client_id": 42,
     *   "token": "...",
     *   "date_naissance": "DD/MM/YYYY",
     *   "sam": 28000,
     *   "trimestres_valides_tous_regimes": 166,
     *   "trimestres_cotises_rg": 158
     * }
     */
    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'                      => 'required|integer',
            'token'                          => 'required|string',
            'date_naissance'                 => 'nullable|string',
            'sam'                            => 'nullable|numeric',
            'trimestres_valides_tous_regimes' => 'nullable|integer',
            'trimestres_cotises_rg'          => 'nullable|integer',
            'user_id'                        => 'nullable|integer',
            'user_context'                   => 'nullable|string',
            'scenario_params'                => 'nullable|array',
        ]);

        $n8nResponse = Http::timeout(120)->post(self::WEBHOOK_URL, $data);

        if (!$n8nResponse->successful()) {
            return response()->json(['message' => 'Le webhook CNAV a retourné une erreur.', 'status' => $n8nResponse->status()], 502);
        }

        $result = $n8nResponse->json();
        if (is_array($result) && isset($result[0])) {
            $result = $result[0];
        }

        return response()->json($result);
    }
}
