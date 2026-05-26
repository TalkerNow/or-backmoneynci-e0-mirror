<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('session_id');
            $table->foreign('session_id')
                  ->references('id')->on('report_chat_sessions')
                  ->onDelete('cascade');

            $table->enum('role', ['user', 'assistant', 'system']);
            $table->longText('content');

            // HTML proposé par l'IA, pas encore appliqué (NULL pour user/system messages)
            $table->longText('proposed_html')->nullable();

            // Pointe vers report_versions.id si le proposed_html a été appliqué
            // Pas de FK pour éviter le cycle avec report_versions.source_message_id
            $table->unsignedBigInteger('applied_version_id')->nullable();

            $table->timestamps();

            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_chat_messages');
    }
};
