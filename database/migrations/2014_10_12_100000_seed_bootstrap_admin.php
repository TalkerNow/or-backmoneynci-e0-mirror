<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Crée un admin "système" id=4 si absent.
 *
 * Plusieurs migrations data (ex. 2026_05_13_100100_seed_registre_erreurs_coherence,
 * 2026_06_03_090000_add_r010...) insèrent dans `prompts.created_by = ADMIN_USER_ID (=4)`,
 * avec une FK vers users(id). Sur une base fraîche (migrate:fresh) aucun user n'existe
 * encore => violation FK. En prod l'admin id=4 préexiste.
 *
 * Idempotent : ne crée l'user que si l'id 4 n'existe pas. En prod => no-op.
 * Login local : admin@local.fr / Admin2026!
 */
class SeedBootstrapAdmin extends Migration
{
    public function up()
    {
        if (DB::table('users')->where('id', 4)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'id'         => 4,
            'name'       => 'Admin Local',
            'email'      => 'admin@local.fr',
            'password'   => Hash::make('Admin2026!'),
            'role'       => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('users')->where('id', 4)->where('email', 'admin@local.fr')->delete();
    }
}
