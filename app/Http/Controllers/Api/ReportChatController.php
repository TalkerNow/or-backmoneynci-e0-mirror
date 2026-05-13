<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisReport;
use App\Models\ReportChatMessage;
use App\Models\ReportVersion;
use App\Services\ReportChatService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ReportChatController extends Controller
{
    public function __construct(private ReportChatService $service)
    {
    }

    /**
     * GET /api/v1/analysis-reports/{analysisReport}/chat
     * Récupère (ou crée) la session pour ce rapport + l'historique complet.
     */
    public function show(AnalysisReport $analysisReport)
    {
        $userId = optional(auth()->user())->id;

        $session = $this->service->getOrCreateSession($analysisReport, $userId);
        $this->service->ensureInitialVersion($analysisReport, $userId);

        $messages = $session->messages()->get(['id', 'role', 'content', 'proposed_html', 'applied_version_id', 'created_at']);

        return response()->json([
            'session_id' => $session->id,
            'messages'   => $messages,
        ]);
    }

    /**
     * POST /api/v1/analysis-reports/{analysisReport}/chat/message
     * Envoie un prompt user, appelle Gemini, retourne le message assistant + proposed_html.
     */
    public function sendMessage(Request $request, AnalysisReport $analysisReport)
    {
        $data = $request->validate([
            'content'             => ['required', 'string', 'max:8000'],
            'extra_skill_codes'   => ['nullable', 'array'],
            'extra_skill_codes.*' => ['string', 'max:100'],
        ]);

        $userId = optional(auth()->user())->id;
        $extraSkillCodes = $data['extra_skill_codes'] ?? [];

        try {
            $result = $this->service->sendMessage($analysisReport, $data['content'], $userId, $extraSkillCodes);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Erreur serveur lors de l\'appel IA'], 500);
        }

        return response()->json([
            'user_message'      => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
        ], 201);
    }

    /**
     * POST /api/v1/analysis-reports/{analysisReport}/chat/messages/{message}/apply
     * Applique le proposed_html d'un message → crée une nouvelle ReportVersion + maj rapport.
     */
    public function applyMessage(AnalysisReport $analysisReport, ReportChatMessage $message)
    {
        if ($message->session->analysis_report_id !== $analysisReport->id) {
            return response()->json(['message' => 'Le message n\'appartient pas à ce rapport.'], 404);
        }

        $userId = optional(auth()->user())->id;

        try {
            $version = $this->service->applyProposedVersion($analysisReport, $message, $userId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'version'         => $version,
            'analysis_report' => $analysisReport->fresh(),
        ], 201);
    }

    /**
     * GET /api/v1/analysis-reports/{analysisReport}/chat/context
     * Renvoie le contexte exact passé à l'IA + le system prompt résolu (debug consultant/admin).
     */
    public function context(Request $request, AnalysisReport $analysisReport)
    {
        $extraSkillCodes = (array) $request->input('extra_skill_codes', []);
        // Filtrer + caster pour éviter les surprises avec ?extra_skill_codes[]=…
        $extraSkillCodes = array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim($v) : null,
            $extraSkillCodes,
        )));

        try {
            return response()->json($this->service->getChatContext($analysisReport, $extraSkillCodes));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /api/v1/analysis-reports/{analysisReport}/versions
     * Liste des versions du HTML (les plus récentes d'abord).
     */
    public function listVersions(AnalysisReport $analysisReport)
    {
        $versions = $analysisReport->versions()
            ->get(['id', 'analysis_report_id', 'source', 'source_message_id', 'created_by', 'created_at']);

        return response()->json($versions);
    }

    /**
     * POST /api/v1/analysis-reports/{analysisReport}/versions/{version}/restore
     * Restaure une ancienne version (en crée une nouvelle pour traçabilité).
     */
    public function restoreVersion(AnalysisReport $analysisReport, ReportVersion $version)
    {
        $userId = optional(auth()->user())->id;

        try {
            $newVersion = $this->service->restoreVersion($analysisReport, $version, $userId);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'version'         => $newVersion,
            'analysis_report' => $analysisReport->fresh(),
        ], 201);
    }
}
