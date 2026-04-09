<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFrozenDataTable extends Migration
{
    public function up()
    {
        Schema::create('frozen_data', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->index();
            $table->string('source')->nullable();        // ex: 'RIS_CNAV_2026'
            $table->json('meta')->nullable();            // {nom, prenom, date_naissance, date_gel, source}
            $table->json('carriere')->nullable();        // array of career records (multi-régime)
            $table->json('alertes')->nullable();         // array of anomalies/alerts
            $table->json('totaux')->nullable();          // {trimestres_cotises, assimiles, total, requis}
            $table->timestamp('locked_at')->nullable();  // null = modifiable, non-null = gelé (immuable)
            $table->integer('locked_by')->nullable();    // user_id du consultant qui a gelé
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('frozen_data');
    }
}
