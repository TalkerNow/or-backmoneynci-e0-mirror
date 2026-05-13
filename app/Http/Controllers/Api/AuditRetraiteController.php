<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\FrozenData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AuditRetraiteController extends Controller
{
    private const SKILL_ID    = 'audit_retraite';
    private const N8N_WEBHOOK = 'https://n8n.srv796541.hstgr.cloud/webhook/audit-retraite';

    /**
     * POST /api/v1/audit-retraite/generate
     *
     * Charge frozen_data + analysis_reports, forward à n8n,
     * stocke le HTML retourné et le renvoie au frontend.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        $clientId = (int) $request->input('client_id');

        $frozen = FrozenData::where('user_id', $clientId)->latest()->first();

        if (! $frozen) {
            return response()->json([
                'error' => 'Données carrière introuvables pour ce client. Veuillez d\'abord valider la carrière.',
            ], 404);
        }

        $meta = $frozen->meta ?? [];
        if (empty($meta['date_naissance'])) {
            $rawDate = DB::table('personal_informations')
                ->where('user_id', $clientId)
                ->value('birth_date');
            if ($rawDate) {
                $meta['date_naissance'] = $rawDate;
            }
        }

        $calculsSkills = AnalysisReport::where('user_id', $clientId)
            ->where('skill_id', '!=', self::SKILL_ID)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($r) => [
                'skill_id'     => $r->skill_id,
                'result_json'  => $r->result_json,
                'calcul_json'  => $r->calcul_json,
                'alertes_json' => $r->alertes_json ?? [],
                'statut'       => $r->statut,
                'updated_at'   => $r->updated_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        $totaux   = $frozen->totaux   ?? [];
        $carriere = $frozen->carriere ?? [];

        $payload = [
            'client_id'    => $clientId,
            'user_context' => $request->input('user_context', ''),
            'dispositions' => $request->input('dispositions', []),
            'profil_client' => [
                'nom'            => $meta['nom']            ?? null,
                'prenom'         => $meta['prenom']         ?? null,
                'date_naissance' => $meta['date_naissance'] ?? null,
                'sexe'           => $meta['sexe']           ?? null,
                'nir'            => $meta['nir']            ?? null,
            ],
            'frozen_data' => [
                'user_id'  => $clientId,
                'meta'     => $meta,
                'totaux'   => $totaux,
                'carriere' => $carriere,
                'cipav'    => $frozen->cipav   ?? [],
                'alertes'  => $frozen->alertes ?? [],
                'locked'   => $frozen->isLocked(),
            ],
            'calculs_skills' => $calculsSkills,
        ];

        try {
            $n8nResponse = Http::timeout(900)->post(self::N8N_WEBHOOK, $payload);

            $body = $n8nResponse->json() ?? [];

            if (! empty($body['html_report'])) {
                AnalysisReport::updateOrCreate(
                    [
                        'user_id'  => $clientId,
                        'skill_id' => self::SKILL_ID,
                        'statut'   => 'brouillon',
                    ],
                    [
                        'frozen_data_id'      => $frozen->id,
                        'result_json'         => $body['html_report'],
                        'calcul_json'         => [],
                        'restitution_json'    => [],
                        'alertes_json'        => [],
                        'arret_critique_json' => [],
                        'statut'              => 'brouillon',
                    ]
                );
            }

            return response()->json([
                'success'     => $n8nResponse->successful(),
                'html_report' => $body['html_report'] ?? null,
                'client_id'   => $clientId,
            ], $n8nResponse->successful() ? 200 : 502);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/audit-retraite/store
     *
     * Appelé par n8n après génération HTML — stocke le résultat.
     * Sans auth JWT : n8n appelle cet endpoint directement.
     */
    public function store(Request $request): JsonResponse
    {
        $clientId    = (int) $request->input('client_id');
        $htmlContent = $request->input('text', '');

        $report = AnalysisReport::create([
            'user_id'     => $clientId,
            'skill_id'    => 'audit_retraite',
            'result_json' => [
                'htmlContent'         => $htmlContent,
                'profil_client'       => $request->input('profil_client', []),
                'regimes'             => $request->input('regimes', []),
                'dispositifs_actives' => $request->input('dispositifs_actives', []),
                'dates_simulees'      => $request->input('dates_simulees', []),
            ],
            'statut' => 'brouillon',
        ]);

        return response()->json($report, 201);
    }
}
