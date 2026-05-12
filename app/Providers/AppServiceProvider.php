<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // ── Garde-fou DB ──────────────────────────────────────────────────
        // Le container php-prod (port 8000) doit cibler la DB `moneynci`.
        // Symptôme observé 2026-05-12 : les workers FPM héritent d'un
        // DB_DATABASE=moneynci_test (DB supprimée le 2026-05-11) issu d'une
        // source non identifiée (probablement un ancien `config:cache` ou un
        // env injecté à la création du container). Dotenv en mode immutable
        // refuse de l'écraser, donc `.env` seul ne suffit pas.
        //
        // On force ici la config DB en dur, AVANT que la moindre requête
        // Eloquent n'ouvre une connexion. Si un jour on veut paramétrer ça
        // par env, mettre la valeur en cache une fois en `php artisan config:cache`.
        $forcedDatabase = 'moneynci';
        if (config('database.connections.mysql.database') !== $forcedDatabase) {
            config([
                'database.connections.mysql.database' => $forcedDatabase,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
