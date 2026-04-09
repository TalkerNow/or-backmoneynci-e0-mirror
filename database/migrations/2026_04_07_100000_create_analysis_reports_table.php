<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('analysis_reports', function (Blueprint $table) {
            $table->id();

            // Lien client
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            // Lien vers la frozen_data utilisée (nullable tant que la table n'existe pas encore)
            $table->unsignedBigInteger('frozen_data_id')->nullable();

            // Skill utilisé (ex: "racl", "cnav", "complementaires")
            $table->string('skill_id', 64);

            // JSON structuré — sortie IA (skill_actif, analyse, alertes, arret_critique)
            $table->json('result_json');

            // JSON structuré — sortie Python (scénarios, montants, contrôles)
            $table->json('calcul_json')->nullable();

            // JSON de restitution finale (rapport narratif pour le consultant)
            $table->json('restitution_json')->nullable();

            // Alertes extraites pour requêtage rapide
            $table->json('alertes_json')->nullable();

            // Arrêt critique (si rule S1 déclenchée)
            $table->json('arret_critique_json')->nullable();

            // Statut du rapport dans le workflow de validation
            $table->enum('statut', ['brouillon', 'valide', 'livre'])->default('brouillon');

            // Qui a validé et quand
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->foreign('validated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');
            $table->timestamp('validated_at')->nullable();

            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['user_id', 'skill_id']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_reports');
    }
};
