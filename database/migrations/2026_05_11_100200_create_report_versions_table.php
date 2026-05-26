<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('analysis_report_id');
            $table->foreign('analysis_report_id')
                  ->references('id')->on('analysis_reports')
                  ->onDelete('cascade');

            $table->longText('html_content');

            // Origine de la version : génération initiale, chat IA, édition manuelle, ou restauration
            $table->enum('source', ['initial', 'ai_chat', 'manual_edit', 'restore'])->default('ai_chat');

            // Pointe vers report_chat_messages.id (si source = ai_chat)
            // Pas de FK pour éviter le cycle avec report_chat_messages.applied_version_id
            $table->unsignedBigInteger('source_message_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->timestamps();

            $table->index(['analysis_report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_versions');
    }
};
