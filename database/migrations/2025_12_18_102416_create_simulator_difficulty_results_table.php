<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulator_difficulty_results', function (Blueprint $table) {
            $table->id();

            // Liens / infos “contact” (optionnel mais pratique)
            $table->unsignedBigInteger('external_contact_id')->nullable()->index(); // ex: 68439
            $table->string('email', 255)->index();
            $table->string('nom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->string('civilite', 30)->nullable(); // femme/homme/...
            $table->string('statut', 50)->nullable();   // autre/...

            $table->date('date_naissance')->nullable();
            $table->string('code_postal', 10)->nullable(); // string > int (safe)
            $table->unsignedSmallInteger('nbr_enfants')->nullable();
            $table->unsignedSmallInteger('score')->nullable();

            // Simulateur difficulté
            $table->string('q1', 50)->nullable();
            $table->string('q2', 50)->nullable();
            $table->string('q3', 50)->nullable();
            $table->string('q4', 50)->nullable();
            $table->string('q5', 50)->nullable();
            $table->string('q6', 50)->nullable();
            $table->string('q7', 50)->nullable();
            $table->string('q8', 50)->nullable();
            $table->string('q9', 50)->nullable();
            $table->string('q10', 100)->nullable(); // ex "non,non_sait"

            $table->boolean('newsletter')->default(false);          // SIMULATEUR_DIFFICULTE_NEWSLETTER
            $table->boolean('automation_recap_retraite')->default(false);
            $table->date('date_depart')->nullable();                // SIMULATEUR_DIFFICULTE_DATE_DEPART

            // Meta Brevo-like
            $table->boolean('email_blacklisted')->default(false);
            $table->boolean('sms_blacklisted')->default(false);
            $table->json('list_ids')->nullable();
            $table->string('list_unsubscribed', 255)->nullable();

            // Pour garder le payload brut au cas où
            $table->json('raw_payload')->nullable();

            // Dates externes (dans ton JSON)
            $table->timestampTz('external_created_at')->nullable();
            $table->timestampTz('external_modified_at')->nullable();

            $table->timestampsTz();

            // Si tu veux empêcher les doublons “même contact + même date_depart”
            // $table->unique(['email', 'date_depart']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_difficulty_results');
    }
};

