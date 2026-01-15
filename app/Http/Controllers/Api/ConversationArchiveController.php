<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConversationArchive;
use Illuminate\Http\Request;

class ConversationArchiveController extends Controller
{
    /**
     * Lister toutes les conversations
     * Option simple: tout renvoyer
     * Option mieux: pagination
     */
    public function index(Request $request)
    {
        // Pagination légère par défaut
        $perPage = (int) ($request->get('per_page', 50));
        $perPage = $perPage > 200 ? 200 : $perPage;

        $convs = ConversationArchive::query()
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($convs);
    }

    /**
     * Créer une conversation
     * Body attendu:
     * {
     *   "summary": "Résumé ...", (optionnel)
     *   "messages": [
     *      {"role":"user","content":"..."},
     *      {"role":"assistant","content":"..."}
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'summary'  => ['nullable', 'string'],
            'messages' => ['required', 'array'],

            // Validation douce de la structure
            'messages.*.role'    => ['nullable', 'string'],
            'messages.*.content' => ['nullable', 'string'],
            'user_id'   => ['nullable', 'integer'],
            'invisible' => ['nullable', 'boolean'],
        ]);

        $conv = ConversationArchive::create([
            'summary'   => $data['summary'] ?? null,
            'messages'  => $data['messages'],
            'user_id'   => $data['user_id'] ?? null,
            'invisible' => $data['invisible'] ?? false,
        ]);

        return response()->json($conv, 201);
    }

    /**
     * Voir une conversation par ID
     */
    public function show(int $id)
    {
        $conv = ConversationArchive::find($id);

        if (!$conv) {
            return response()->json([
                'message' => 'Conversation introuvable.',
            ], 404);
        }

        return response()->json($conv);
    }

    /**
     * Modifier une conversation
     * Body possible:
     * {
     *   "summary": "Nouveau résumé",
     *   "messages": [...]
     * }
     */
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
        ]);

        // Update partiel propre
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

        $conv->save();

        return response()->json($conv);
    }

    /**
     * Supprimer une conversation
     */
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

