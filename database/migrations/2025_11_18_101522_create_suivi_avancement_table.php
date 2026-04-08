<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSuiviAvancementTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('suivi_avancement', function (Blueprint $table) {
            $table->id();

            // FK vers client
            $table->unsignedBigInteger('client_id');

            // FK vers facture
            $table->unsignedBigInteger('facture_id');

            // 7 dates pour les 7 steps
            $table->timestamp('step1_completed_at')->nullable();
            $table->timestamp('step2_completed_at')->nullable();
            $table->timestamp('step3_completed_at')->nullable();
            $table->timestamp('step4_completed_at')->nullable();
            $table->timestamp('step5_completed_at')->nullable();
            $table->timestamp('step6_completed_at')->nullable();
            $table->timestamp('step7_completed_at')->nullable();

            $table->timestamps();

            // Contrainte: un seul suivi par client + facture
            $table->unique(['client_id', 'facture_id']);

            // Si tu as les tables clients / factures :
            // $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            // $table->foreign('facture_id')->references('id')->on('factures')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('suivi_avancement');
    }
}

