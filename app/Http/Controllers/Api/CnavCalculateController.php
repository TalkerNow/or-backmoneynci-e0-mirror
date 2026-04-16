<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CnavCalculateController extends Controller
{
    private const WEBHOOK_URL = 'https://n8n.srv796541.hstgr.cloud/webhook/f68ecf2b-4ee9-448c-bf61-f7b381148dc3';

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
            'client_id'  => 'required|integer',
            'token'      => 'required|string',
            'user_id'    => 'nullable|integer',
            'user_context' => 'nullable|string',
        ]);

        // v1 workflow requires skill_code in the body
        $data['skill_code'] = 'CNAV';

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
