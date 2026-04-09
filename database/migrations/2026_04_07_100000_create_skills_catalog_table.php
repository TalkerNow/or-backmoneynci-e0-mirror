<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkillsCatalogTable extends Migration
{
    public function up()
    {
        Schema::create('skills_catalog', function (Blueprint $table) {
            $table->id();

            // Identifiant unique du skill (ex: "SKILL_calcul_cnav_v1")
            $table->string('skill_id', 100)->unique();

            // Métadonnées affichables (Progressive Disclosure Level 1 — Signature)
            $table->string('nom', 255);           // "Calcul Pension CNAV"
            $table->string('code', 50)->index();   // "CNAV" — c'est la clé de recherche principale
            $table->string('version', 20)->default('1.0');
            $table->string('type', 100);           // "skill_calcul_regime", "skill_validation", etc.
            $table->text('description')->nullable();

            // Contenu complet (Progressive Disclosure Level 2+3 — chargé à la demande)
            $table->longText('skill_md');           // Contenu du fichier SKILL_*.md (contexte réglementaire)
            $table->json('regles_json');             // Contenu du fichier *_regles.json (règles structurées)
            $table->longText('calcul_py')->nullable(); // Contenu du fichier calcul_*.py (null si pas de script Python)

            // Catégorisation
            $table->json('tags')->nullable();        // ["cnav", "regime_base", "pension"]
            $table->integer('priority')->default(5); // 0 = le plus prioritaire (validation d'abord, calculs ensuite)
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('skills_catalog');
    }
}
