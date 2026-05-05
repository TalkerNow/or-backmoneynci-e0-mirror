<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddScenariosDatesArraysToFrozenData extends Migration
{
    /**
     * Multi-select : remplace les colonnes singulières scenario_choisi /
     * date_retenue par des tableaux. Les anciennes colonnes restent en place
     * (back-compat lecture). Backfill : si singulier rempli, le porter dans
     * le tableau.
     *
     * scenarios_choisis : tableau d'objets
     *   {
     *     "dispositif_id": "racl",
     *     "label": "...",
     *     "skill_code": "RACL",
     *     "params": { "input": "..." },
     *     "result_summary": { "eligible": true, ... },
     *     "last_calc": { ...résultat complet du dernier calcul... },
     *     "last_calc_at": "ISO-8601",
     *     "chosen_at": "ISO-8601",
     *     "chosen_by": <user id>
     *   }
     *
     * dates_retenues : tableau d'objets — identité = type|date
     *   { "type": "...", "label": "...", "date": "YYYY-MM-DD", "info": "...",
     *     "chosen_at": "...", "chosen_by": <id> }
     */
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (!Schema::hasColumn('frozen_data', 'scenarios_choisis')) {
                $table->json('scenarios_choisis')->nullable()->after('date_retenue')
                      ->comment('Tableau des scénarios retenus par le consultant (multi-select)');
            }
            if (!Schema::hasColumn('frozen_data', 'dates_retenues')) {
                $table->json('dates_retenues')->nullable()->after('scenarios_choisis')
                      ->comment('Tableau des dates de départ retenues par le consultant (multi-select)');
            }
        });

        // Backfill : porter scenario_choisi (singulier) → scenarios_choisis[0]
        // et date_retenue → dates_retenues[0] pour préserver l'historique.
        DB::table('frozen_data')
            ->whereNotNull('scenario_choisi')
            ->whereNull('scenarios_choisis')
            ->orderBy('id')
            ->each(function ($row) {
                $singular = json_decode($row->scenario_choisi, true);
                if (is_array($singular) && !empty($singular)) {
                    DB::table('frozen_data')->where('id', $row->id)
                        ->update(['scenarios_choisis' => json_encode([$singular])]);
                }
            });

        DB::table('frozen_data')
            ->whereNotNull('date_retenue')
            ->whereNull('dates_retenues')
            ->orderBy('id')
            ->each(function ($row) {
                $singular = json_decode($row->date_retenue, true);
                if (is_array($singular) && !empty($singular)) {
                    DB::table('frozen_data')->where('id', $row->id)
                        ->update(['dates_retenues' => json_encode([$singular])]);
                }
            });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (Schema::hasColumn('frozen_data', 'dates_retenues')) {
                $table->dropColumn('dates_retenues');
            }
            if (Schema::hasColumn('frozen_data', 'scenarios_choisis')) {
                $table->dropColumn('scenarios_choisis');
            }
        });
    }
}
