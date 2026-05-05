<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScenarioChoisiToFrozenData extends Migration
{
    /**
     * Ajoute scenario_choisi : choix de scénario retenu par le consultant
     * pour le client, pris APRES la validation/lock de la carrière.
     *
     * Forme attendue (JSON) :
     *   {
     *     "dispositif_id": "racl",
     *     "label": "Carrière longue (RACL)",
     *     "skill_code": "RACL",
     *     "params": {...},
     *     "result_summary": {...},
     *     "chosen_at": "2026-05-05T10:30:00Z",
     *     "chosen_by": 42
     *   }
     *
     * Cette colonne reste modifiable même si locked_at est non-null
     * (le choix de scénario est une décision post-gel).
     */
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (!Schema::hasColumn('frozen_data', 'scenario_choisi')) {
                $table->json('scenario_choisi')->nullable()->after('totaux')
                      ->comment('Scénario retenu par le consultant (modifiable post-lock)');
            }
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (Schema::hasColumn('frozen_data', 'scenario_choisi')) {
                $table->dropColumn('scenario_choisi');
            }
        });
    }
}
