<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultantsAccessTable extends Migration
{
    public function up()
    {
        Schema::create('consultants_access', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->date('date_de_naissance');
            $table->enum('access_type', ['unlimited_pass', 'credits']);
            $table->dateTime('pass_expiration_date')->nullable();
            $table->unsignedInteger('remaining_credits')->default(0);
            $table->timestamps();

            $table->index(['nom', 'prenom', 'date_de_naissance'], 'idx_identity');
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultants_access');
    }
}
