<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserKanbansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_kanbans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('kanban_id')->constrained('kanbans')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->dateTime('date');
            $table->string('status')->nullable();
            $table->timestamps();

            // Index pour améliorer les performances
            $table->index(['user_id', 'kanban_id']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_kanbans');
    }
}
