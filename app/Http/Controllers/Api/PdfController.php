<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    /**
     * Proxy pour récupérer le contenu HTML d'une URL (contourne CORS)
     * POST /api/fetch-html
     */
    public function fetchHtml(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'timeout' => 30,
                ]
            ]);
            
            $htmlContent = file_get_contents($request->url, false, $context);
            
            if ($htmlContent === false) {
                return response()->json(['error' => 'Impossible de récupérer le document'], 500);
            }

            return response()->json([
                'success' => true,
                'html' => $htmlContent
            ]);
                
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}