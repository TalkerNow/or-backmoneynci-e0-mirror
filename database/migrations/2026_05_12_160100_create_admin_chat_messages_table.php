<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminChatMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('admin_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');
            $table->json('metadata')->nullable();
            // Logical reference to admin_chat_snapshots — no DB FK to avoid circular constraint
            $table->unsignedBigInteger('applied_modification_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')->references('id')->on('admin_chat_sessions')->onDelete('cascade');
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_chat_messages');
    }
}
