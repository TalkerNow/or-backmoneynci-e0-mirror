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
        // Force DB name from FORCE_DB_DATABASE env var (prod workaround for FPM env
        // inheritance issues). If not set, env('DB_DATABASE') from .env is used as-is.
        $forcedDatabase = env('FORCE_DB_DATABASE');
        if ($forcedDatabase && config('database.connections.mysql.database') !== $forcedDatabase) {
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
