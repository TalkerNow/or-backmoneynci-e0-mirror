<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRegimesPointsToFrozenDataTable extends Migration
{
    /**
     * Colonne JSON générique pour stocker les points par régime simple (Tier 1).
     * Forme : { CARMF: { base: 1500, complementaire: 800 }, CAVP: {...}, ... }
     * Évite d'ajouter une colonne par nouveau régime.
     */
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->json('regimes_points')->nullable()->after('carpimko');
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->dropColumn('regimes_points');
        });
    }
}
