<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDateRetenueToFrozenData extends Migration
{
    /**
     * Ajoute date_retenue : date de départ retenue par le consultant
     * pour ce client. Décision post-validation, modifiable même si locked.
     *
     * Forme JSON :
     *   {
     *     "type": "age_legal" | "taux_plein" | "taux_plein_auto" | "date_libre",
     *     "label": "Âge légal",
     *     "date": "2023-06-01",
     *     "info": "62 ans 3 mois → juin 2023",
     *     "chosen_at": "2026-05-05T10:30:00Z",
     *     "chosen_by": 42
     *   }
     */
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (!Schema::hasColumn('frozen_data', 'date_retenue')) {
                $table->json('date_retenue')->nullable()->after('scenario_choisi')
                      ->comment('Date de départ retenue par le consultant (modifiable post-lock)');
            }
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (Schema::hasColumn('frozen_data', 'date_retenue')) {
                $table->dropColumn('date_retenue');
            }
        });
    }
}
