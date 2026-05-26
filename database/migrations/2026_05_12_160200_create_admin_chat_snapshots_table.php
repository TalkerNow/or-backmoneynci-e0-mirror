<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminChatSnapshotsTable extends Migration
{
    public function up()
    {
        Schema::create('admin_chat_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('entity_type', ['prompt', 'system_prompt', 'skill_md', 'skill_regles_json', 'skill_calcul_py']);
            $table->unsignedBigInteger('entity_id');
            $table->longText('content_before');
            $table->longText('content_after');
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamp('reverted_at')->nullable();

            $table->foreign('session_id')->references('id')->on('admin_chat_sessions')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('admin_chat_messages')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'applied_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_chat_snapshots');
    }
}
