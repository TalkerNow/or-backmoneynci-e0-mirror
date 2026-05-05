<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\FrozenData;
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
     * enrichit avec frozen_data + analysis_reports du simulateur,
     * forward à n8n en multipart. CDC-compliant : frontend → backend → n8n.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'file'      => 'required|file',
            'client_id' => 'required',
        ]);

        $file     = $request->file('file');
        $message  = $request->input('message', '');
        $clientId = (int) $request->input('client_id');

        // Charger les données simulateur depuis la DB
        $frozenData     = FrozenData::where('user_id', $clientId)->latest()->first();
        $analysisReports = AnalysisReport::where('user_id', $clientId)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('skill_id');

        // Construire le contexte simulateur à injecter dans n8n
        $simulateurContext = [];

        if ($frozenData) {
            $simulateurContext['frozen_data'] = [
                'carriere' => $frozenData->carriere,
                'cipav'    => $frozenData->cipav,
                'totaux'   => $frozenData->totaux,
                'alertes'  => $frozenData->alertes,
                'meta'     => $frozenData->meta,
                'locked'   => $frozenData->isLocked(),
            ];
        }

        if ($analysisReports->isNotEmpty()) {
            $simulateurContext['calculs'] = $analysisReports->map(fn($r) => [
                'skill_id'            => $r->skill_id,
                'result_json'         => $r->result_json,
                'alertes_json'        => $r->alertes_json,
                'arret_critique_json' => $r->arret_critique_json,
                'updated_at'          => $r->updated_at?->toIso8601String(),
            ])->values()->toArray();
        }

        try {
            $n8nRequest = Http::timeout(360)
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName());

            if ($message) {
                $n8nRequest = $n8nRequest->attach('message', $message, null);
            }
            $n8nRequest = $n8nRequest->attach('client_id', (string) $clientId, null);

            if ($request->input('system_prompt')) {
                $n8nRequest = $n8nRequest->attach('system_prompt', $request->input('system_prompt'), null);
            }

            // Injecter les données simulateur si disponibles
            if (!empty($simulateurContext)) {
                $n8nRequest = $n8nRequest->attach(
                    'simulateur_context',
                    json_encode($simulateurContext, JSON_UNESCAPED_UNICODE),
                    null
                );
            }

            $n8nResponse = $n8nRequest->post(self::N8N_WEBHOOK);

            // Persistance immédiate côté backend (EOR-61) :
            // si la génération aboutit, on sauve le HTML brut dans analysis_reports
            // AVANT de répondre. Le frontend pourra ensuite raffiner (cleanup, upload),
            // mais si l'utilisateur navigue entre-temps ou ferme le navigateur,
            // le mount loader retrouvera quand même le livrable.
            if ($n8nResponse->successful()) {
                $rawHtml = $this->extractHtml($n8nResponse->json() ?? $n8nResponse->body());
                if ($rawHtml !== null && $rawHtml !== '') {
                    AnalysisReport::updateOrCreate(
                        ['user_id' => $clientId, 'skill_id' => 'rapport_consultation'],
                        ['result_json' => $rawHtml, 'statut' => 'brouillon']
                    );
                }
            }

            return response()->json([
                'success'    => $n8nResponse->successful(),
                'n8n_status' => $n8nResponse->status(),
                'data'       => $n8nResponse->json() ?? $n8nResponse->body(),
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extrait le HTML d'une réponse n8n hétérogène
     * (string, tableau, objet avec html_report/output/text/response).
     */
    private function extractHtml($data): ?string
    {
        if ($data === null) return null;
        $root = is_array($data) && array_keys($data) === range(0, count($data) - 1) ? ($data[0] ?? null) : $data;
        if (is_string($root)) return $root;
        if (is_array($root)) {
            foreach (['html_report', 'output', 'text', 'response'] as $key) {
                if (!empty($root[$key]) && is_string($root[$key])) return $root[$key];
            }
        }
        return null;
    }
}
