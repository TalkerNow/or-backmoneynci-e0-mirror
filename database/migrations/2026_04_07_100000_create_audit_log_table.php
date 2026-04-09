<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogTable extends Migration
{
    public function up()
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();

            // Contexte déclenchement
            $table->integer('user_id')->index();           // consultant qui a déclenché
            $table->integer('client_id')->index();          // client cible
            $table->integer('frozen_data_id')->nullable();  // snapshot utilisé (frozen_data.id)

            // Skill déclenché
            $table->string('skill_name');                   // ex: 'audit-carriere-longue'
            $table->string('skill_version')->nullable();    // semver ex: '1.0.0'
            $table->integer('system_prompt_id')->nullable(); // prompts.id utilisé

            // LLM
            $table->string('model_used');                   // ex: 'gemini-2.5-flash-preview-09-2025'
            $table->json('llm_request')->nullable();        // prompt assemblé envoyé au LLM
            $table->json('llm_response_raw')->nullable();   // réponse brute avant post-traitement
            $table->integer('tokens_input')->nullable();
            $table->integer('tokens_output')->nullable();

            // Python service (nullable — pas toujours appelé)
            $table->json('python_request')->nullable();     // payload envoyé au service Python
            $table->json('python_response_raw')->nullable(); // réponse brute Python

            // Contexte utilisateur
            $table->text('user_context')->nullable();       // commentaire libre du consultant

            // Résultat
            $table->string('status');                       // 'success' | 'error' | 'retry'
            $table->text('error_message')->nullable();      // rempli si status != 'success'
            $table->integer('latency_ms')->nullable();      // durée totale de l'exécution

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_log');
    }
}
