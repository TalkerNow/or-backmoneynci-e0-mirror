<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSimulatorChatTables extends Migration
{
    public function up()
    {
        Schema::create('simulator_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');       // consultant
            $table->unsignedBigInteger('customer_id');   // client consulté
            $table->string('title')->default('Nouvelle session');
            $table->string('context_page')->default('simulateur_client');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'customer_id']);
        });

        Schema::create('simulator_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')->references('id')->on('simulator_chat_sessions')->onDelete('cascade');
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('simulator_chat_messages');
        Schema::dropIfExists('simulator_chat_sessions');
    }
}
