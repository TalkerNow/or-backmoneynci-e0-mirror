<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute la règle R010 (AVERTISSEMENT tranche C) au registre Gate #2.
 *
 * APPEND idempotent : on n'écrase PAS tout le prompt (préserve les règles
 * ajoutées via l'UI), on insère uniquement le bloc R010 extrait du doc source
 * `docs/REGISTRE_ERREURS_COHERENCE.md` (source unique du texte de la règle).
 */
class AddR010TrancheCToRegistreErreurs extends Migration
{
    private const ADMIN_USER_ID = 4;
    private const PROMPT_NAME   = 'REGISTRE_ERREURS_COHERENCE';
    private const TEMPLATE_MARK = '## 📋 TEMPLATE POUR AJOUTER';

    public function up()
    {
        $row = DB::table('prompts')->where('name', self::PROMPT_NAME)->first();
        if (!$row) {
            // Registre pas encore seedé : le seed initial inclura R010 via le doc.
            return;
        }
        if (str_contains((string) $row->prompt_text, '### R010')) {
            return; // déjà présent → idempotent
        }

        $doc = @file_get_contents(base_path('docs/REGISTRE_ERREURS_COHERENCE.md'));
        if ($doc === false || !preg_match('/### R010 \|.*?\n---\n/s', $doc, $m)) {
            throw new \RuntimeException('Bloc R010 introuvable dans docs/REGISTRE_ERREURS_COHERENCE.md');
        }
        $block = rtrim($m[0]) . "\n";

        $text = (string) $row->prompt_text;
        if (str_contains($text, self::TEMPLATE_MARK)) {
            $new = str_replace(self::TEMPLATE_MARK, $block . "\n" . self::TEMPLATE_MARK, $text);
        } else {
            $new = rtrim($text) . "\n\n" . $block;
        }

        // Versioning manuel (Prompt::boot() ne se déclenche pas en migration).
        $latest = DB::table('prompt_history')->where('prompt_id', $row->id)->max('version') ?? 0;
        DB::table('prompt_history')->insert([
            'prompt_id'   => $row->id,
            'version'     => $latest + 1,
            'prompt_text' => $row->prompt_text,
            'created_by'  => self::ADMIN_USER_ID,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('prompts')->where('id', $row->id)->update([
            'prompt_text' => $new,
            'updated_at'  => now(),
        ]);
    }

    public function down()
    {
        $row = DB::table('prompts')->where('name', self::PROMPT_NAME)->first();
        if (!$row || !str_contains((string) $row->prompt_text, '### R010')) {
            return;
        }
        $new = preg_replace('/### R010 \|.*?\n---\n\n?/s', '', (string) $row->prompt_text);
        DB::table('prompts')->where('id', $row->id)->update([
            'prompt_text' => $new,
            'updated_at'  => now(),
        ]);
    }
}
