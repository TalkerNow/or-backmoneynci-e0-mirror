<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE kpis
            ADD COLUMN note VARCHAR(255) NULL AFTER telephone
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE kpis
            DROP COLUMN note
        ");
    }
};

