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
        'CNAV'                  => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cnav-v2-test',
        'AGIRC_ARRCO'           => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-agirc-arrco-v2-test',
        'IRCANTEC'              => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-ircantec-v2-test',
        'RCI'                   => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-rci-v2-test',
        'CIPAV'                 => 'https://n8n.srv796541.hstgr.cloud/webhook/script-execute-cipav-v2-test',
        'RACL'                  => 'https://n8n.srv796541.hstgr.cloud/webhook/racl-executor-v1-test',
        'COTISATIONS_MIN'       => 'https://n8n.srv796541.hstgr.cloud/webhook/tns-executor-v1-test',
        'ARRET_ACTIVITE'        => 'https://n8n.srv796541.hstgr.cloud/webhook/arret-activite-v1-test',
        'CHOMAGE_INDEMNISE'     => 'https://n8n.srv796541.hstgr.cloud/webhook/chomage-indemnise-v1-test',
        'CHOMAGE_NON_INDEMNISE' => 'https://n8n.srv796541.hstgr.cloud/webhook/chomage-non-indemnise-v1-test',
        'VPLR_INCOMPLETE'       => 'https://n8n.srv796541.hstgr.cloud/webhook/vplr-annee-incomplete-v1-test',
        'VPLR_ETUDE'            => 'https://n8n.srv796541.hstgr.cloud/webhook/vplr-annee-etude-v1-test',
        'CER'                   => 'https://n8n.srv796541.hstgr.cloud/webhook/cer-executor-v1-test',
        'RP'                    => 'https://n8n.srv796541.hstgr.cloud/webhook/rp-executor-v1-test',
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

        // PHP encode les tableaux vides en [] mais Python attend {} pour scenario_params
        if (empty($payload['scenario_params'])) {
            $payload['scenario_params'] = new \stdClass();
        }

        // Tous les régimes : injecter frozen_data dans le payload
        // pour que n8n n'ait jamais besoin de rappeler le serveur
        if (in_array($regimeCode, array_keys(self::WEBHOOKS))) {
            $frozen = $this->frozenRepo->getByUserId($data['client_id']);
            if (!$frozen) {
                return response()->json([
                    'message' => "Aucune frozen_data trouvée pour le client {$data['client_id']}. Lancez d'abord le calcul RIS.",
                ], 422);
            }

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

        $timeout = 120;
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
