<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedRegistreErreursCoherence extends Migration
{
    // [TO_CONFIRM_WITH_JF] created_by = 4 (premier admin ADMIN_IDS)
    private const ADMIN_USER_ID = 4;
    private const PROMPT_NAME   = 'REGISTRE_ERREURS_COHERENCE';

    public function up()
    {
        $mdPath  = base_path('docs/REGISTRE_ERREURS_COHERENCE.md');
        $content = file_get_contents($mdPath);

        if ($content === false) {
            throw new \RuntimeException("Fichier introuvable : {$mdPath}");
        }

        $existing = DB::table('prompts')->where('name', self::PROMPT_NAME)->first();

        if ($existing) {
            DB::table('prompts')
                ->where('name', self::PROMPT_NAME)
                ->update([
                    'prompt_text' => $content,
                    'updated_at'  => now(),
                ]);

            // Manually trigger history versioning (Prompt::boot() won't fire in migrations)
            $latestVersion = DB::table('prompt_history')
                ->where('prompt_id', $existing->id)
                ->max('version') ?? 0;

            DB::table('prompt_history')->insert([
                'prompt_id'   => $existing->id,
                'version'     => $latestVersion + 1,
                'prompt_text' => $existing->prompt_text,
                'created_by'  => self::ADMIN_USER_ID,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('prompts')->insert([
                'name'        => self::PROMPT_NAME,
                'description' => 'Source de vérité Gate #2 — registre auto-apprentissage des erreurs de cohérence',
                'type'        => 'autre', // [TO_CONFIRM_WITH_JF] pas de valeur registre dans l'ENUM
                'prompt_text' => $content,
                'created_by'  => self::ADMIN_USER_ID,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down()
    {
        DB::table('prompts')->where('name', self::PROMPT_NAME)->delete();
    }
}
