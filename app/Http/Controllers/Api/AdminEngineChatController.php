<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminChatMemory;
use App\Models\AdminChatMemoryHistory;
use App\Models\AdminChatMessage;
use App\Models\AdminChatSession;
use App\Models\AdminChatSnapshot;
use App\Models\Prompt;
use App\Models\SkillsCatalog;
use App\Models\SystemPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEngineChatController extends Controller
{
    private const ADMIN_IDS    = [4, 1271, 1638];
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
        if (!in_array((int) $user->id, self::ADMIN_IDS)) {
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
                $row = SystemPrompt::find($entityId);
                return $row ? $row->content : null;

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
                $row = SystemPrompt::findOrFail($entityId);
                $row->update(['content' => $newContent]);
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
