<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminChatMemory;
use App\Models\AdminChatMemoryHistory;
use App\Models\AdminChatMessage;
use App\Models\AdminChatSession;
use App\Models\AdminChatSnapshot;
use App\Models\Prompt;
use App\Models\ReportedError;
use App\Models\SkillsCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEngineChatController extends Controller
{
    private const PERMISSION    = 'admin-moteur';
    private const ENTITY_TYPES = ['prompt', 'system_prompt', 'skill_md', 'skill_regles_json', 'skill_calcul_py'];
    private const MAX_SNAPSHOTS = 50;

    private function getAuthUser()
    {
        try {
            return auth('api')->userOrFail();
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            abort(401, 'Non authentifié.');
        }
    }

    private function assertAdmin($user): void
    {
        if (!$user->hasPermission(self::PERMISSION)) {
            abort(403, 'Accès réservé aux administrateurs.');
        }
    }

    // ─── Sessions ────────────────────────────────────────────────────────────

    public function listSessions(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $sessions = AdminChatSession::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'title', 'context_page', 'created_at', 'updated_at']);

        return response()->json($sessions);
    }

    public function createSession(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $data = $request->validate([
            'context_page' => ['required', 'in:admin_moteur,prompts_page'],
            'title'        => ['nullable', 'string', 'max:255'],
        ]);

        $session = AdminChatSession::create([
            'user_id'      => $user->id,
            'title'        => $data['title'] ?? 'Nouvelle session',
            'context_page' => $data['context_page'],
        ]);

        return response()->json($session, 201);
    }

    public function getSession(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $session = AdminChatSession::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = $session->messages()->get([
            'id', 'session_id', 'role', 'content', 'metadata', 'applied_modification_id', 'created_at',
        ]);

        return response()->json([
            'session'  => $session,
            'messages' => $messages,
        ]);
    }

    public function deleteSession(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $session = AdminChatSession::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $session->delete();

        return response()->json(['message' => 'Session supprimée.']);
    }

    // ─── Message ─────────────────────────────────────────────────────────────

    public function sendMessage(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $session = AdminChatSession::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'content' => ['required', 'string', 'max:16000'],
        ]);

        // Persist user message
        $userMessage = AdminChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => $data['content'],
        ]);

        // Load memory
        $memory = AdminChatMemory::where('user_id', $user->id)->first();
        $memoryContent = $memory ? $memory->content : '';

        // Build history for n8n payload (last 20 messages for context)
        $history = $session->messages()
            ->where('id', '<', $userMessage->id)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $catalog = [
            'skills'  => SkillsCatalog::where('active', true)
                ->get(['id', 'code', 'nom', 'skill_md', 'regles_json'])
                ->toArray(),
            'prompts' => Prompt::all(['id', 'name', 'type', 'prompt_text'])->toArray(),
        ];

        $n8nPayload = [
            'session_id'   => $session->id,
            'context_page' => $session->context_page,
            'user'         => [
                'id'   => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'role' => $user->role,
            ],
            'memory'    => $memoryContent,
            'history'   => $history,
            'message'   => $data['content'],
            'catalog'   => $catalog,
            'timestamp' => now()->toIso8601String(),
        ];

        $webhookUrl = env('N8N_ADMIN_CHAT_WEBHOOK', '');
        $assistantContent = '';
        $metadata = [];

        if (!empty($webhookUrl)) {
            try {
                $client   = new \GuzzleHttp\Client(['timeout' => 60]);
                $response = $client->post($webhookUrl, ['json' => $n8nPayload]);
                $body     = json_decode($response->getBody()->getContents(), true);

                $assistantContent = $body['message'] ?? $body['content'] ?? json_encode($body);
                $metadata = [
                    'model'      => $body['model'] ?? null,
                    'tokens_in'  => $body['tokens_input'] ?? null,
                    'tokens_out' => $body['tokens_output'] ?? null,
                    'latency_ms' => $body['latency_ms'] ?? null,
                ];
            } catch (\Throwable $e) {
                $assistantContent = '[ERREUR] Impossible de joindre le workflow n8n : ' . $e->getMessage();
            }
        } else {
            $assistantContent = '[STUB] Message reçu : "' . $data['content'] . '". Workflow n8n non configuré.';
        }

        $assistantMessage = AdminChatMessage::create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'content'    => $assistantContent,
            'metadata'   => !empty($metadata) ? $metadata : null,
        ]);

        // Touch session updated_at
        $session->touch();

        return response()->json([
            'user_message'      => $userMessage,
            'assistant_message' => $assistantMessage,
        ], 201);
    }

    // ─── Apply ───────────────────────────────────────────────────────────────

    public function applyModification(Request $request, $id)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $session = AdminChatSession::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'message_id'  => ['required', 'integer', 'exists:admin_chat_messages,id'],
            'entity_type' => ['required', 'in:' . implode(',', self::ENTITY_TYPES)],
            'entity_id'   => ['required', 'integer'],
            'new_content' => ['required', 'string'],
        ]);

        // Validate entity exists
        $contentBefore = $this->getEntityContent($data['entity_type'], $data['entity_id']);
        if ($contentBefore === null) {
            return response()->json(['error' => 'Entité introuvable.'], 404);
        }

        $snapshot = DB::transaction(function () use ($session, $data, $user, $contentBefore) {
            $snapshot = AdminChatSnapshot::create([
                'session_id'    => $session->id,
                'message_id'    => $data['message_id'],
                'user_id'       => $user->id,
                'entity_type'   => $data['entity_type'],
                'entity_id'     => $data['entity_id'],
                'content_before' => $contentBefore,
                'content_after'  => $data['new_content'],
                'applied_at'    => now(),
            ]);

            $this->setEntityContent($data['entity_type'], $data['entity_id'], $data['new_content']);

            // Link snapshot to message
            AdminChatMessage::where('id', $data['message_id'])
                ->update(['applied_modification_id' => $snapshot->id]);

            return $snapshot;
        });

        $this->pruneSnapshots($user->id);

        return response()->json($snapshot, 201);
    }

    // ─── Revert ──────────────────────────────────────────────────────────────

    public function revertSnapshot(Request $request, $snapshotId)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $snapshot = AdminChatSnapshot::where('id', $snapshotId)
            ->where('user_id', $user->id)
            ->whereNull('reverted_at')
            ->firstOrFail();

        DB::transaction(function () use ($snapshot, $user) {
            $this->setEntityContent($snapshot->entity_type, $snapshot->entity_id, $snapshot->content_before);

            $snapshot->update(['reverted_at' => now()]);

            // Create a revert snapshot so re-revert is possible
            AdminChatSnapshot::create([
                'session_id'     => $snapshot->session_id,
                'message_id'     => $snapshot->message_id,
                'user_id'        => $user->id,
                'entity_type'    => $snapshot->entity_type,
                'entity_id'      => $snapshot->entity_id,
                'content_before' => $snapshot->content_after,
                'content_after'  => $snapshot->content_before,
                'applied_at'     => now(),
            ]);
        });

        return response()->json(['message' => 'Modification annulée.']);
    }

    // ─── Memory ──────────────────────────────────────────────────────────────

    public function getMemory(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $memory = AdminChatMemory::where('user_id', $user->id)->first();

        return response()->json([
            'content'    => $memory ? $memory->content : '',
            'updated_at' => $memory ? $memory->updated_at : null,
        ]);
    }

    public function updateMemory(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $data = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $memory = AdminChatMemory::where('user_id', $user->id)->first();

        if ($memory) {
            $memory->update(['content' => $data['content']]);
        } else {
            $memory = AdminChatMemory::create([
                'user_id' => $user->id,
                'content' => $data['content'],
            ]);
        }

        return response()->json([
            'content'    => $memory->content,
            'updated_at' => $memory->updated_at,
        ]);
    }

    public function getMemoryHistory(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $memory = AdminChatMemory::where('user_id', $user->id)->first();

        if (!$memory) {
            return response()->json([]);
        }

        $history = $memory->history()->take(30)->get(['id', 'content', 'created_at']);

        return response()->json($history);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function getEntityContent(string $entityType, int $entityId): ?string
    {
        switch ($entityType) {
            case 'prompt':
                $row = Prompt::find($entityId);
                return $row ? $row->prompt_text : null;

            case 'system_prompt':
                $row = Prompt::find($entityId);
                return $row ? $row->prompt_text : null;

            case 'skill_md':
                $row = SkillsCatalog::find($entityId);
                return $row ? $row->skill_md : null;

            case 'skill_regles_json':
                $row = SkillsCatalog::find($entityId);
                return $row ? json_encode($row->regles_json) : null;

            case 'skill_calcul_py':
                $row = SkillsCatalog::find($entityId);
                return $row ? $row->calcul_py : null;
        }

        return null;
    }

    private function setEntityContent(string $entityType, int $entityId, string $newContent): void
    {
        switch ($entityType) {
            case 'prompt':
                $row = Prompt::findOrFail($entityId);
                $row->update(['prompt_text' => $newContent]);
                break;

            case 'system_prompt':
                $row = Prompt::findOrFail($entityId);
                $row->update(['prompt_text' => $newContent]);
                break;

            case 'skill_md':
                $row = SkillsCatalog::findOrFail($entityId);
                $row->update(['skill_md' => $newContent]);
                break;

            case 'skill_regles_json':
                $row = SkillsCatalog::findOrFail($entityId);
                $decoded = json_decode($newContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('regles_json invalide : ' . json_last_error_msg());
                }
                $row->update(['regles_json' => $decoded]);
                break;

            case 'skill_calcul_py':
                $row = SkillsCatalog::findOrFail($entityId);
                $row->update(['calcul_py' => $newContent]);
                break;
        }
    }

    // ─── Registry ────────────────────────────────────────────────────────────

    public function getRegistry(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $prompt = Prompt::where('name', 'REGISTRE_ERREURS_COHERENCE')->firstOrFail();

        return response()->json([
            'raw_markdown' => $prompt->prompt_text,
            'parsed'       => $this->parseRegistryMarkdown($prompt->prompt_text),
            'prompt_id'    => $prompt->id,
        ]);
    }

    public function reportError(Request $request)
    {
        $user = $this->getAuthUser();

        if (!in_array($user->role, ['admin', 'Admin', 'Consultant', 'Expert'])) {
            abort(403, 'Accès non autorisé.');
        }

        $data = $request->validate([
            'client_id'   => ['nullable', 'integer', 'exists:users,id'],
            'section'     => ['required', 'in:carriere,scenarios_dates,livrables,autre'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $error = ReportedError::create([
            'user_id'     => $user->id,
            'client_id'   => $data['client_id'] ?? null,
            'section'     => $data['section'],
            'description' => $data['description'],
            'status'      => 'pending',
        ]);

        return response()->json($error, 201);
    }

    public function appendRule(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $data = $request->validate([
            'title'                    => ['required', 'string', 'max:255'],
            'prompt_concerne'          => ['required', 'string', 'max:255'],
            'erreur_detectee'          => ['required', 'string'],
            'condition_python'         => ['nullable', 'string'],
            'message_erreur'           => ['required', 'string'],
            'niveau'                   => ['nullable', 'string'],
            'impact'                   => ['required', 'string'],
            'cas_origine'              => ['nullable', 'string', 'max:255'],
            'source_reported_error_id' => ['nullable', 'integer', 'exists:reported_errors,id'],
        ]);

        $prompt = Prompt::where('name', 'REGISTRE_ERREURS_COHERENCE')->firstOrFail();

        // Auto-generate code: count active R0XX rules + 1
        preg_match_all('/^### (R\d{3})\s*\|/m', $prompt->prompt_text, $codeMatches);
        $existingNumbers = array_map(fn($c) => (int) substr($c, 1), $codeMatches[1] ?? []);
        $nextNumber = count($existingNumbers) > 0 ? (max($existingNumbers) + 1) : 1;
        $data['code']       = 'R' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        $data['date_ajout'] = now()->format('d/m/Y');
        $data['consultant'] = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Admin';
        $data['statut']     = '✅ ACTIF';
        $data['niveau']     = $data['niveau'] ?: '🔴 CRITIQUE (bloquant)';
        $data['cas_origine']      = $data['cas_origine'] ?? 'Signalement via chat admin';
        $data['condition_python'] = $data['condition_python'] ?: '# à définir';

        $newBlock = $this->buildRuleMarkdownBlock($data);

        // Insert at end of active rules section (before ## 📋 TEMPLATE, or before ## 🟢, or append)
        $markers = ['## 📋 TEMPLATE', '## 🟢 RÈGLES ARCHIVÉES'];
        $newContent = null;
        foreach ($markers as $marker) {
            if (str_contains($prompt->prompt_text, $marker)) {
                $newContent = str_replace($marker, $newBlock . "\n---\n\n" . $marker, $prompt->prompt_text);
                break;
            }
        }
        if ($newContent === null) $newContent = $prompt->prompt_text . "\n---\n\n" . $newBlock;

        DB::transaction(function () use ($prompt, $newContent, $data, $user) {
            // Snapshot BEFORE update
            AdminChatSnapshot::create([
                'session_id'     => null,
                'message_id'     => null,
                'user_id'        => $user->id,
                'entity_type'    => 'prompt',
                'entity_id'      => $prompt->id,
                'content_before' => $prompt->prompt_text,
                'content_after'  => $newContent,
                'applied_at'     => now(),
            ]);

            // Update triggers Prompt::boot() → prompt_history versioning
            $prompt->update(['prompt_text' => $newContent]);

            if (!empty($data['source_reported_error_id'])) {
                ReportedError::where('id', $data['source_reported_error_id'])
                    ->update(['status' => 'converted_to_rule']);
            }
        });

        return response()->json(['message' => 'Règle ajoutée.', 'code' => $data['code']], 201);
    }

    public function detectTrigger(Request $request)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:16000'],
        ]);

        $msg = mb_strtolower(preg_replace('/\s+/', ' ', trim($data['message'])));

        $triggers = [
            ['pattern' => 'cette erreur ne doit plus se reproduire', 'phrase' => 'cette erreur ne doit plus se reproduire', 'prompt' => null],
            ['pattern' => 'bloquer cette erreur',                    'phrase' => 'bloquer cette erreur',                    'prompt' => null],
            ['pattern' => 'nouvelle règle de cohérence',             'phrase' => 'nouvelle règle de cohérence',             'prompt' => null],
            ['pattern' => 'ajouter règle gate',                      'phrase' => 'ajouter règle gate #2',                   'prompt' => null],
        ];

        foreach ($triggers as $t) {
            if (str_contains($msg, $t['pattern'])) {
                return response()->json([
                    'triggered'              => true,
                    'matched_phrase'         => $t['phrase'],
                    'suggested_prompt_number' => null,
                ]);
            }
        }

        // Pattern: prompt [1|2|3] erreur
        if (preg_match('/prompt\s*([123])\s*erreur/i', $data['message'], $m)) {
            return response()->json([
                'triggered'               => true,
                'matched_phrase'          => 'prompt ' . $m[1] . ' erreur',
                'suggested_prompt_number' => (int) $m[1],
            ]);
        }

        return response()->json([
            'triggered'               => false,
            'matched_phrase'          => null,
            'suggested_prompt_number' => null,
        ]);
    }

    // ─── Registry helpers ─────────────────────────────────────────────────────

    private function parseRegistryMarkdown(string $md): array
    {
        // Délégué au parser pur partagé (cf. RegistreRules / enforcement).
        return (new \App\Services\Registre\RegistreParser())->parse($md);
    }

    private function parseAllRules(string $md): array
    {
        return (new \App\Services\Registre\RegistreParser())->parseAll($md);
    }

    private function buildRuleMarkdownBlock(array $data): string
    {
        return <<<MD
### {$data['code']} | {$data['title']}
**Date d'ajout** : {$data['date_ajout']}
**Cas origine** : {$data['cas_origine']}
**Prompt concerné** : {$data['prompt_concerne']}
**Consultant** : {$data['consultant']}
**Erreur détectée** : {$data['erreur_detectee']}
**Condition Python** : `{$data['condition_python']}`
**Message d'erreur** : "{$data['message_erreur']}"
**Niveau** : {$data['niveau']}
**Statut** : {$data['statut']}

**Impact** : {$data['impact']}
MD;
    }

    public function toggleRuleStatus(Request $request, string $code)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $code   = strtoupper($code);
        $prompt = Prompt::where('name', 'REGISTRE_ERREURS_COHERENCE')->firstOrFail();

        // Extract this rule's block (stop at next --- or next ### or end)
        $codeQ = preg_quote($code, '/');
        if (!preg_match('/(### ' . $codeQ . '\s*\|[\s\S]*?)(?=\n---|\n### R\d|\z)/u', $prompt->prompt_text, $blockMatch)) {
            return response()->json(['error' => 'Règle introuvable.'], 404);
        }

        $block   = $blockMatch[1];
        $isActif = str_contains($block, '✅ ACTIF');
        $from    = $isActif ? '✅ ACTIF' : '❌ INACTIF';
        $to      = $isActif ? '❌ INACTIF' : '✅ ACTIF';

        // Replace statut only inside this block, then substitute block back into full text
        $newBlock   = preg_replace('/(\*\*Statut\*\*\s*:)\s*' . preg_quote($from, '/') . '/u', '$1 ' . $to, $block, 1);
        $newContent = str_replace($block, $newBlock, $prompt->prompt_text);

        DB::transaction(function () use ($prompt, $newContent, $user) {
            AdminChatSnapshot::create([
                'session_id' => null, 'message_id' => null, 'user_id' => $user->id,
                'entity_type' => 'prompt', 'entity_id' => $prompt->id,
                'content_before' => $prompt->prompt_text, 'content_after' => $newContent,
                'applied_at' => now(),
            ]);
            $prompt->update(['prompt_text' => $newContent]);
        });

        return response()->json(['code' => $code, 'statut' => $to]);
    }

    public function deleteRule(Request $request, string $code)
    {
        $user = $this->getAuthUser();
        $this->assertAdmin($user);

        $code   = strtoupper($code);
        $prompt = Prompt::where('name', 'REGISTRE_ERREURS_COHERENCE')->firstOrFail();

        // Match rule block: from ### RXXX | to next --- or next ### or end of active section
        $newContent = preg_replace(
            '/### ' . preg_quote($code, '/') . '\s*\|[\s\S]*?(?=\n---\n|\n### R\d|\n## |\z)/u',
            '',
            $prompt->prompt_text
        );

        // Clean up orphaned --- separators
        $newContent = preg_replace('/\n---\n\n---\n/', "\n---\n", $newContent);
        $newContent = preg_replace('/\n---\n(\n## )/', '$1', $newContent);

        DB::transaction(function () use ($prompt, $newContent, $user) {
            AdminChatSnapshot::create([
                'session_id' => null, 'message_id' => null, 'user_id' => $user->id,
                'entity_type' => 'prompt', 'entity_id' => $prompt->id,
                'content_before' => $prompt->prompt_text, 'content_after' => $newContent,
                'applied_at' => now(),
            ]);
            $prompt->update(['prompt_text' => $newContent]);
        });

        return response()->json(['message' => "Règle {$code} supprimée."]);
    }

    private function pruneSnapshots(int $userId): void
    {
        $ids = AdminChatSnapshot::where('user_id', $userId)
            ->whereNull('reverted_at')
            ->orderBy('applied_at', 'desc')
            ->skip(self::MAX_SNAPSHOTS)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            AdminChatSnapshot::whereIn('id', $ids)->delete();
        }
    }
}
