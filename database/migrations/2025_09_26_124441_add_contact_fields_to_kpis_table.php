<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE kpis
            ADD COLUMN nom_prenom VARCHAR(255) NULL AFTER action,
            ADD COLUMN email VARCHAR(255) NULL AFTER nom_prenom,
            ADD COLUMN telephone VARCHAR(255) NULL AFTER email
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE kpis
            DROP COLUMN telephone,
            DROP COLUMN email,
            DROP COLUMN nom_prenom
        ");
    }
};


