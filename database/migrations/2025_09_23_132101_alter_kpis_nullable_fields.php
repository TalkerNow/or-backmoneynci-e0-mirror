<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterKpisNullableFields extends Migration
{
    public function up(): void
    {
        // rendre les champs NULL
        DB::statement("ALTER TABLE kpis MODIFY kpi_date DATE NULL");
        DB::statement("ALTER TABLE kpis MODIFY objet VARCHAR(255) NULL");
        DB::statement("ALTER TABLE kpis MODIFY action VARCHAR(255) NULL");

        // FK admin_id : drop FK, rendre NULL, recréer en ON DELETE SET NULL
        DB::statement("ALTER TABLE kpis DROP FOREIGN KEY kpis_admin_id_foreign");
        DB::statement("ALTER TABLE kpis MODIFY admin_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE kpis ADD CONSTRAINT kpis_admin_id_foreign
                       FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL");
    }

    public function down(): void
    {
        // revenir en NOT NULL + FK en CASCADE (ou ce que tu avais)
        DB::statement("ALTER TABLE kpis DROP FOREIGN KEY kpis_admin_id_foreign");
        DB::statement("ALTER TABLE kpis MODIFY admin_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE kpis ADD CONSTRAINT kpis_admin_id_foreign
                       FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE");

        DB::statement("ALTER TABLE kpis MODIFY kpi_date DATE NOT NULL");
        DB::statement("ALTER TABLE kpis MODIFY objet VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE kpis MODIFY action VARCHAR(255) NOT NULL");
    }
}


