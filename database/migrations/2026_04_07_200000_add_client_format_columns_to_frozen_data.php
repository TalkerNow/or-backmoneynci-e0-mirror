<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClientFormatColumnsToFrozenData extends Migration
{
    /**
     * Ajoute les colonnes format client (00_ARCHITECTURE_IA.md §III) à frozen_data :
     *   - meta     : { date_naissance, sexe, nir, nom, prenom }
     *   - carriere : [{ annee, revenu_brut, regime, trimestres, nature }]
     *   - alertes  : [{ niveau, code, message, bloquant }]
     *   - totaux   : { trimestres_valides, regimes, sam_estime }
     *
     * Migration non-destructive : les anciennes colonnes (profil, carriere_synthese, etc.)
     * sont conservées pour ne pas casser le frontend existant.
     *
     * Guards Schema::hasColumn : idempotente — safe si les colonnes existent déjà
     * (cas où feat/frozen-data-barrier est mergé avant cette migration).
     */
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            if (!Schema::hasColumn('frozen_data', 'meta')) {
                $table->json('meta')->nullable()->after('source')
                      ->comment('Format client: {date_naissance, sexe, nir, nom, prenom}');
            }

            if (!Schema::hasColumn('frozen_data', 'carriere')) {
                $table->json('carriere')->nullable()->after('meta')
                      ->comment('Format client: [{annee, revenu_brut, regime, trimestres, nature}]');
            }

            if (!Schema::hasColumn('frozen_data', 'alertes')) {
                $table->json('alertes')->nullable()->after('carriere')
                      ->comment('Format client: [{niveau, code, message, bloquant}]');
            }

            if (!Schema::hasColumn('frozen_data', 'totaux')) {
                $table->json('totaux')->nullable()->after('alertes')
                      ->comment('Format client: {trimestres_valides, regimes, sam_estime}');
            }
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $columns = ['meta', 'carriere', 'alertes', 'totaux'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('frozen_data', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
