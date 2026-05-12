<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultantPromptNote;
use Illuminate\Http\Request;

class ConsultantPromptNoteController extends Controller
{
    /**
     * GET /api/v1/clients/{clientId}/prompt-notes
     * Liste des notes du consultant courant pour ce client (20 dernières, desc).
     */
    public function index(int $clientId)
    {
        $consultantId = optional(auth()->user())->id;
        if (!$consultantId) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $notes = ConsultantPromptNote::where('client_id', $clientId)
            ->where('consultant_id', $consultantId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'content', 'created_at']);

        return response()->json(['notes' => $notes]);
    }

    /**
     * POST /api/v1/clients/{clientId}/prompt-notes
     * Body: { content: string }
     */
    public function store(Request $request, int $clientId)
    {
        $consultantId = optional(auth()->user())->id;
        if (!$consultantId) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $content = trim($data['content']);
        if ($content === '') {
            return response()->json(['message' => 'Contenu vide'], 422);
        }

        $existing = ConsultantPromptNote::where('client_id', $clientId)
            ->where('consultant_id', $consultantId)
            ->where('content', $content)
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $existing->touch();
            return response()->json(['note' => $existing], 200);
        }

        $note = ConsultantPromptNote::create([
            'client_id'     => $clientId,
            'consultant_id' => $consultantId,
            'content'       => $content,
        ]);

        return response()->json(['note' => $note], 201);
    }

    /**
     * DELETE /api/v1/prompt-notes/{id}
     */
    public function destroy(int $id)
    {
        $consultantId = optional(auth()->user())->id;
        if (!$consultantId) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $note = ConsultantPromptNote::where('id', $id)
            ->where('consultant_id', $consultantId)
            ->first();

        if (!$note) {
            return response()->json(['message' => 'Note introuvable'], 404);
        }

        $note->delete();

        return response()->json(['message' => 'Note supprimée']);
    }
}
