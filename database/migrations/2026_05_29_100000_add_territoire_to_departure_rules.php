<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une dimension `territoire` au barème des âges de départ.
 *
 * Aujourd'hui la table ne porte que la métropole / régime général (21 lignes,
 * cf. DepartureRulesSeeder), et l'import PDF n'extrait que la métropole. Cette
 * colonne — par défaut 'metropole' — laisse la porte ouverte aux variantes
 * territoriales (Saint-Pierre-et-Miquelon, Mayotte…) sans re-migrer plus tard.
 * Tant qu'aucune ligne non-métropole n'existe, le comportement est inchangé.
 */
class AddTerritoireToDepartureRules extends Migration
{
    public function up(): void
    {
        Schema::table('departure_rules', function (Blueprint $table) {
            $table->string('territoire', 32)->default('metropole')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('departure_rules', function (Blueprint $table) {
            $table->dropColumn('territoire');
        });
    }
}
