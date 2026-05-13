<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminChatSessionsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->default('Nouvelle session');
            $table->enum('context_page', ['admin_moteur', 'prompts_page'])->default('admin_moteur');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_chat_sessions');
    }
}
