<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationArchive;
use Illuminate\Http\Request;

class ConversationArchiveController extends Controller
{
    /**
     * Lister toutes les conversations
     */
    public function index(Request $request)
    {
        $perPage = (int) ($request->get('per_page', 50));
        $perPage = $perPage > 200 ? 200 : $perPage;

        $q = ConversationArchive::query()
            ->with('user')
            ->orderByDesc('id');

        if ($request->has('is_read')) {
            $raw = $request->get('is_read');
            if ($raw === '0' || $raw === 0 || $raw === false || $raw === 'false') {
                $q->where('is_read', false);
            } elseif ($raw === '1' || $raw === 1 || $raw === true || $raw === 'true') {
                $q->where('is_read', true);
            }
        }

        $convs = $q->paginate($perPage);

        return response()->json($convs);
    }

    /**
     * GET /api/conversation-archives/unread-count — badge Chatbot
     * Counts visible (not invisible) unread conversations.
     */
    public function unreadCount(Request $request)
    {
        $q = ConversationArchive::query()
            ->where('is_read', false)
            ->where(function ($w) {
                $w->whereNull('invisible')->orWhere('invisible', false);
            });

        return response()->json(['count' => (int) $q->count()]);
    }

    /**
     * Créer une conversation — new rows default unread (is_read=0).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'summary'  => ['nullable', 'string'],
            'source'   => ['nullable', 'string', 'in:eor,expert-retraite'],
            'messages' => ['required', 'array'],

            'messages.*.role'    => ['nullable', 'string'],
            'messages.*.content' => ['nullable', 'string'],
            'user_id'   => ['nullable', 'integer'],
            'invisible' => ['nullable', 'boolean'],
        ]);

        $conv = ConversationArchive::create([
            'summary'   => $data['summary'] ?? null,
            'source'    => $data['source'] ?? 'eor',
            'messages'  => $data['messages'],
            'user_id'   => $data['user_id'] ?? null,
            'invisible' => $data['invisible'] ?? false,
            'is_read'   => false,
            'read_at'   => null,
        ]);

        $conv->load('user');

        return response()->json($conv, 201);
    }

    public function show(int $id)
    {
        $conv = ConversationArchive::with('user')->find($id);

        if (!$conv) {
            return response()->json([
                'message' => 'Conversation introuvable.',
            ], 404);
        }

        return response()->json($conv);
    }

    public function update(Request $request, int $id)
    {
        $conv = ConversationArchive::find($id);

        if (!$conv) {
            return response()->json([
                'message' => 'Conversation introuvable.',
            ], 404);
        }

        $data = $request->validate([
            'summary'  => ['sometimes', 'nullable', 'string'],
            'messages' => ['sometimes', 'required', 'array'],

            'messages.*.role'    => ['nullable', 'string'],
            'messages.*.content' => ['nullable', 'string'],
            'user_id'   => ['nullable', 'integer'],
            'invisible' => ['nullable', 'boolean'],
            'is_read'   => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('summary', $data)) {
            $conv->summary = $data['summary'];
        }

        if (array_key_exists('messages', $data)) {
            $conv->messages = $data['messages'];
        }

        if (array_key_exists('user_id', $data)) {
            $conv->user_id = $data['user_id'];
        }

        if (array_key_exists('invisible', $data)) {
            $conv->invisible = $data['invisible'];
        }

        if (array_key_exists('is_read', $data)) {
            $conv->is_read = $data['is_read'];
            $conv->read_at = $data['is_read'] ? now() : null;
        }

        $conv->save();
        $conv->load('user');

        return response()->json($conv);
    }

    /**
     * PATCH /api/conversation-archives/{id}/read — mark read (auth:api)
     */
    public function markRead(int $id)
    {
        $conv = ConversationArchive::find($id);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }
        $conv->is_read = true;
        $conv->read_at = now();
        $conv->save();
        $conv->load('user');
        return response()->json($conv);
    }

    /**
     * PATCH /api/conversation-archives/{id}/unread — mark unread (auth:api)
     */
    public function markUnread(int $id)
    {
        $conv = ConversationArchive::find($id);
        if (!$conv) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }
        $conv->is_read = false;
        $conv->read_at = null;
        $conv->save();
        $conv->load('user');
        return response()->json($conv);
    }

    public function destroy(int $id)
    {
        $conv = ConversationArchive::find($id);

        if (!$conv) {
            return response()->json([
                'message' => 'Conversation introuvable.',
            ], 404);
        }

        $conv->delete();

        return response()->json([
            'message' => 'Conversation supprimée.',
        ]);
    }
}
