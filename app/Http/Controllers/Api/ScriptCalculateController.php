<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\FrozenDataRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ScriptCalculateController extends Controller
{
    private const WEBHOOKS = [
        'CNAV'        => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cnav-v2-test',
        'AGIRC_ARRCO' => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-agirc-arrco-v2-test',
        'IRCANTEC'    => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-ircantec-v2-test',
        'RCI'         => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-rci-v2-test',
        'CIPAV'       => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cipav-v2-test',
        'RACL'        => 'https://n8n.srv796541.hstgr.cloud/webhook/racl-executor-v1-test',
    ];

    public function __construct(private FrozenDataRepository $frozenRepo) {}

    /**
     * POST /api/script/calculate
     *
     * Proxy multi-régimes vers n8n — évite CORS depuis le navigateur.
     * Pour RACL : enrichit le payload avec frozen_data depuis la DB locale
     * afin que le workflow n8n n'ait pas besoin de rappeler le serveur.
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

        // RACL : injecter frozen_data dans le payload pour que n8n
        // n'ait pas besoin de rappeler le serveur (fonctionne en local)
        if ($regimeCode === 'RACL') {
            $frozen = $this->frozenRepo->getByUserId($data['client_id']);
            if ($frozen) {
                $frozenArray = $frozen->toArray();

                // Garantir date_naissance dans meta (fallback DB si absent)
                if (empty($frozenArray['meta']['date_naissance'])) {
                    $rawDate = DB::table('personal_informations')
                        ->where('user_id', $data['client_id'])
                        ->value('birth_date');
                    if ($rawDate) {
                        $frozenArray['meta']['date_naissance'] = $rawDate;
                    }
                }

                $payload['frozen_data'] = $frozenArray;
            }
        }

        $timeout = $regimeCode === 'RACL' ? 90 : 120;
        $n8nResponse = Http::timeout($timeout)->post(self::WEBHOOKS[$regimeCode], $payload);

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
