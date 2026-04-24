<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RapportConsultationController extends Controller
{
    const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/f012dfc7-8b2c-479f-af1f-20dcd44cda02';

    /**
     * POST /api/v1/rapports/consultation
     *
     * Reçoit le PDF RIS + contexte client depuis le frontend,
     * forward à n8n en multipart. CDC-compliant : frontend → backend → n8n.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file',
            'client_id' => 'required',
        ]);

        $file    = $request->file('file');
        $message = $request->input('message', '');

        try {
            $n8nRequest = Http::timeout(360)
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName());

            if ($message) {
                $n8nRequest = $n8nRequest->attach('message', $message, null);
            }
            if ($request->input('client_id')) {
                $n8nRequest = $n8nRequest->attach('client_id', (string) $request->input('client_id'), null);
            }
            if ($request->input('system_prompt')) {
                $n8nRequest = $n8nRequest->attach('system_prompt', $request->input('system_prompt'), null);
            }

            $n8nResponse = $n8nRequest->post(self::N8N_WEBHOOK);

            return response()->json([
                'success'    => $n8nResponse->successful(),
                'n8n_status' => $n8nResponse->status(),
                'data'       => $n8nResponse->json() ?? $n8nResponse->body(),
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
