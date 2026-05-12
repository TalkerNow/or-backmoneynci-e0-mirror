<?php

namespace App\Services;

use App\Models\AnalysisReport;
use App\Models\FrozenData;
use App\Models\ReportChatMessage;
use App\Models\ReportChatSession;
use App\Models\ReportVersion;
use App\Services\Prompts\RapportConsultationPrompt;
use App\Services\Prompts\SimulationRetraitePrompt;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrateur du chat IA d'édition de livrable.
 *
 * Responsabilités :
 *  - récupère/crée la session associée à un AnalysisReport
 *  - assemble le contexte (HTML courant + frozen + client + calcul) selon skill_id
 *  - construit le prompt système et l'historique pour Gemini
 *  - persiste les messages user + assistant
 *  - parse la réponse IA (texte + HTML proposé)
 *  - applique un proposed_html (création de ReportVersion + maj AnalysisReport)
 */
class ReportChatService
{
    public function __construct(private GeminiClient $gemini)
    {
    }

    /**
     * Retourne le contexte exact envoyé à l'IA pour ce livrable (debug consultant/admin).
     *
     * @return array{ context: array, system_prompt: string }
     */
    public function getChatContext(AnalysisReport $report): array
    {
        $context = $this->buildContext($report);
        $systemPrompt = $this->buildSystemPrompt($report->skill_id, $context);

        return [
            'context'       => $context,
            'system_prompt' => $systemPrompt,
        ];
    }

    public function getOrCreateSession(AnalysisReport $report, ?int $userId): ReportChatSession
    {
        $session = ReportChatSession::where('analysis_report_id', $report->id)->first();
        if ($session) {
            return $session;
        }

        return ReportChatSession::create([
            'analysis_report_id' => $report->id,
            'created_by'         => $userId,
        ]);
    }

    /**
     * Envoie un message utilisateur, appelle Gemini, persiste la conversation.
     * Ne modifie PAS le HTML du livrable — uniquement stocke proposed_html dans le message assistant.
     *
     * @return array{ user_message: ReportChatMessage, assistant_message: ReportChatMessage }
     */
    public function sendMessage(AnalysisReport $report, string $userContent, ?int $userId): array
    {
        $userContent = trim($userContent);
        if ($userContent === '') {
            throw new InvalidArgumentException('Le message ne peut pas être vide.');
        }

        $session = $this->getOrCreateSession($report, $userId);

        // 1. Persister le message user
        $userMsg = ReportChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $userContent,
        ]);

        // 2. Construire le contexte + prompt système
        $context = $this->buildContext($report);
        $systemPrompt = $this->buildSystemPrompt($report->skill_id, $context);

        // 3. Récupérer l'historique (sauf le message user qu'on vient de créer, qui sera passé séparément)
        $history = $session->messages()
            ->where('id', '<', $userMsg->id)
            ->whereIn('role', ['user', 'assistant'])
            ->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // 4. Appeler Gemini
        $rawResponse = $this->gemini->chat($systemPrompt, $history, $userContent);

        // 5. Parser la réponse (séparer commentaire + HTML proposé)
        [$comment, $proposedHtml] = $this->parseAiResponse($rawResponse);

        // 6. Persister le message assistant
        $assistantMsg = ReportChatMessage::create([
            'session_id'    => $session->id,
            'role'          => 'assistant',
            'content'       => $comment,
            'proposed_html' => $proposedHtml,
        ]);

        return [
            'user_message'      => $userMsg,
            'assistant_message' => $assistantMsg,
        ];
    }

    /**
     * Applique le proposed_html d'un message assistant : crée une ReportVersion
     * et met à jour AnalysisReport.result_json.htmlContent.
     */
    public function applyProposedVersion(AnalysisReport $report, ReportChatMessage $message, ?int $userId): ReportVersion
    {
        if ($message->role !== 'assistant') {
            throw new InvalidArgumentException('Seul un message assistant peut être appliqué.');
        }
        if (empty($message->proposed_html)) {
            throw new InvalidArgumentException('Ce message ne contient pas de HTML proposé.');
        }
        if ($message->applied_version_id) {
            throw new InvalidArgumentException('Ce message a déjà été appliqué.');
        }

        return DB::transaction(function () use ($report, $message, $userId) {
            $version = ReportVersion::create([
                'analysis_report_id' => $report->id,
                'html_content'       => $message->proposed_html,
                'source'             => 'ai_chat',
                'source_message_id'  => $message->id,
                'created_by'         => $userId,
            ]);

            $message->applied_version_id = $version->id;
            $message->save();

            $this->updateReportHtml($report, $message->proposed_html);

            return $version;
        });
    }

    /**
     * Restaure une ancienne version : copie son HTML comme version courante.
     * Crée une NOUVELLE ReportVersion (source=restore) pour traçabilité.
     */
    public function restoreVersion(AnalysisReport $report, ReportVersion $version, ?int $userId): ReportVersion
    {
        if ($version->analysis_report_id !== $report->id) {
            throw new InvalidArgumentException('Cette version ne correspond pas à ce rapport.');
        }

        return DB::transaction(function () use ($report, $version, $userId) {
            $newVersion = ReportVersion::create([
                'analysis_report_id' => $report->id,
                'html_content'       => $version->html_content,
                'source'             => 'restore',
                'source_message_id'  => null,
                'created_by'         => $userId,
            ]);

            $this->updateReportHtml($report, $version->html_content);

            return $newVersion;
        });
    }

    /**
     * Snapshot la version actuelle si aucune version n'existe encore (pour avoir un point de départ).
     */
    public function ensureInitialVersion(AnalysisReport $report, ?int $userId): void
    {
        if ($report->versions()->exists()) {
            return;
        }
        $currentHtml = $this->extractCurrentHtml($report);
        if ($currentHtml === '') {
            return;
        }
        ReportVersion::create([
            'analysis_report_id' => $report->id,
            'html_content'       => $currentHtml,
            'source'             => 'initial',
            'source_message_id'  => null,
            'created_by'         => $userId,
        ]);
    }

    private function buildContext(AnalysisReport $report): array
    {
        $report->loadMissing(['user', 'frozenData']);

        // Fallback : pour les rapports créés avant que frozen_data_id ne soit câblé
        // dans SimulationRetraiteController, la relation est null. On rattache alors
        // le dernier FrozenData du même user pour que le chat ait quand même la donnée.
        $frozen = $report->frozenData;
        if (! $frozen && $report->user_id) {
            $frozen = FrozenData::where('user_id', $report->user_id)->latest()->first();
        }

        return [
            'client' => [
                'name'  => $report->user->name ?? null,
                'email' => $report->user->email ?? null,
            ],
            'frozen_data' => $frozen ? $frozen->toArray() : null,
            'calcul_json' => $report->calcul_json,
            'current_html' => $this->extractCurrentHtml($report),
        ];
    }

    private function buildSystemPrompt(string $skillId, array $context): string
    {
        switch ($skillId) {
            case 'simulation_retraite':
                return SimulationRetraitePrompt::build($context);
            case 'rapport_consultation':
                return RapportConsultationPrompt::build($context);
            default:
                throw new RuntimeException("Aucun prompt système défini pour le skill_id '{$skillId}'.");
        }
    }

    /**
     * Le HTML du livrable est stocké dans result_json, mais sous deux formes selon le skill_id :
     *  - simulation_retraite : result_json = chaîne HTML brute
     *  - rapport_consultation, audit_retraite : result_json = objet { htmlContent: "...", ... }
     */
    private function extractCurrentHtml(AnalysisReport $report): string
    {
        $json = $report->result_json;
        if (is_string($json)) {
            return $json;
        }
        if (is_array($json)) {
            return (string) ($json['htmlContent'] ?? '');
        }
        return '';
    }

    private function updateReportHtml(AnalysisReport $report, string $newHtml): void
    {
        $current = $report->result_json;
        if (is_array($current)) {
            $current['htmlContent'] = $newHtml;
            $report->result_json = $current;
        } else {
            // String ou null : on stocke directement la nouvelle chaîne HTML
            $report->result_json = $newHtml;
        }
        $report->save();
    }

    /**
     * Sépare le commentaire (texte libre) du HTML proposé (bloc ```html ... ```).
     * Si pas de bloc HTML détecté, le proposed_html est null (cas d'une question de clarification).
     *
     * @return array{0: string, 1: string|null}
     */
    private function parseAiResponse(string $raw): array
    {
        // Cherche un bloc ```html ... ``` (insensible aux whitespaces autour de "html")
        if (preg_match('/```\s*html\s*\n(.*?)```/is', $raw, $matches)) {
            $html = trim($matches[1]);
            $comment = trim(preg_replace('/```\s*html\s*\n.*?```/is', '', $raw));
            return [$comment, $html];
        }

        // Fallback : un bloc ``` quelconque qui contient une balise <html ou <!DOCTYPE
        if (preg_match('/```\s*\n(.*?)```/is', $raw, $matches)
            && preg_match('/<!doctype|<html|<body/i', $matches[1])) {
            $html = trim($matches[1]);
            $comment = trim(preg_replace('/```\s*\n.*?```/is', '', $raw));
            return [$comment, $html];
        }

        // Pas de HTML détecté → message conversationnel uniquement
        return [trim($raw), null];
    }
}
