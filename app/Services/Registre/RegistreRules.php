<?php

namespace App\Services\Registre;

use App\Models\Prompt;

/**
 * Sélection des règles ACTIVES du registre d'erreurs, normalisées pour le
 * payload n8n (enforcement Gate #2). Mirroir de App\Services\Circulaires\Selector.
 *
 * Source unique de vérité : la ligne prompts.REGISTRE_ERREURS_COHERENCE.
 */
class RegistreRules
{
    public const PROMPT_NAME = 'REGISTRE_ERREURS_COHERENCE';

    public function __construct(private ?RegistreParser $parser = null)
    {
        $this->parser = $parser ?: new RegistreParser();
    }

    /**
     * Charge le registre depuis la DB et renvoie ses règles actives normalisées.
     * Tolérant : renvoie [] si le prompt n'existe pas (rien à enforcer).
     *
     * @return array<int,array{code:string,condition:string,message:string,niveau:string}>
     */
    public function selectActiveRules(): array
    {
        $prompt = Prompt::where('name', self::PROMPT_NAME)->first();
        if (!$prompt) {
            return [];
        }

        return $this->activeRulesFromMarkdown($prompt->prompt_text ?? '');
    }

    /**
     * Pur : parse le markdown, ne garde que les règles actives (statut ✅) et
     * les normalise vers {code, condition, message, niveau}.
     *
     * @return array<int,array{code:string,condition:string,message:string,niveau:string}>
     */
    public function activeRulesFromMarkdown(string $md): array
    {
        $parsed = $this->parser->parse($md);
        $out = [];

        foreach ($parsed['active_rules'] as $rule) {
            $niveauRaw = (string) ($rule['niveau'] ?? '');
            $out[] = [
                'code'      => (string) ($rule['code'] ?? ''),
                'condition' => trim((string) ($rule['condition_python'] ?? '')),
                'message'   => (string) ($rule['message_erreur'] ?? ''),
                'niveau'    => str_contains(mb_strtoupper($niveauRaw), 'CRITIQUE') ? 'CRITIQUE' : 'AVERTISSEMENT',
            ];
        }

        return $out;
    }
}
