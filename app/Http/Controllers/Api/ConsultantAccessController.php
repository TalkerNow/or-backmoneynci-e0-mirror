<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ConsultantAccessController extends Controller
{
    private const WEBHOOK_URL = ''; // Remplir : N8N_CONSULTANT_ACCESS_WEBHOOK_URL dans .env

    /**
     * POST /api/v1/consultant-access/verify
     *
     * Proxy vers le webhook n8n "Pass/Access Consultants".
     * Retourne 200 {authorized: true} ou 403 {error: ...} selon le résultat n8n.
     *
     * Body JSON : last_name, first_name, date_of_birth (YYYY-MM-DD)
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'last_name'     => 'required|string|max:100',
            'first_name'    => 'required|string|max:100',
            'date_of_birth' => 'required|date_format:Y-m-d',
        ]);

        $webhookUrl = config('services.n8n.consultant_access_url', self::WEBHOOK_URL);

        if (empty($webhookUrl)) {
            return response()->json(['error' => 'Webhook non configuré.'], 503);
        }

        $n8nResponse = Http::timeout(15)->post($webhookUrl, [
            'last_name'     => $request->input('last_name'),
            'first_name'    => $request->input('first_name'),
            'date_of_birth' => $request->input('date_of_birth'),
        ]);

        if ($n8nResponse->status() === 200) {
            return response()->json(['authorized' => true], 200);
        }

        if ($n8nResponse->status() === 403) {
            return response()->json([
                'error' => $n8nResponse->json('error') ?? 'Accès refusé.',
            ], 403);
        }

        return response()->json(['error' => 'Erreur lors de la vérification d\'accès.'], 502);
    }
}
