<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportedErrorsTable extends Migration
{
    public function up()
    {
        Schema::create('reported_errors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->enum('section', ['carriere', 'scenarios_dates', 'livrables', 'autre']);
            $table->text('description');
            $table->enum('status', ['pending', 'converted_to_rule', 'dismissed'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reported_errors');
    }
}
