<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultantHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('consultant_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('consultant_id')->nullable();
            $table->string('consultant_name')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultant_history');
    }
}
