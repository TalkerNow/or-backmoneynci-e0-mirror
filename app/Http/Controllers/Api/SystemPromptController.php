<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prompt;

class SystemPromptController extends Controller
{
    /**
     * GET /api/v1/system-prompt/latest
     *
     * Retourne le dernier system prompt de type 'analyse' (EOR Calculator).
     * Utilisé par n8n pour charger le prompt depuis la BDD plutôt que depuis une variable.
     * Le prompt est identifié par name = 'EOR_SYSTEM_PROMPT' (seeded par EorSystemPromptSeeder).
     */
    public function latest()
    {
        $prompt = Prompt::where('name', 'EOR SystemPrompt — Moteur Analyse Réglementaire')
            ->latest('updated_at')
            ->first();

        if (!$prompt) {
            return response()->json([
                'error' => 'System prompt not found',
                'hint'  => 'Run php artisan db:seed --class=EorSystemPromptSeeder',
            ], 404);
        }

        return response()->json([
            'id'          => $prompt->id,
            'name'        => $prompt->name,
            'prompt_text' => $prompt->prompt_text,
            'updated_at'  => $prompt->updated_at,
        ]);
    }
}
