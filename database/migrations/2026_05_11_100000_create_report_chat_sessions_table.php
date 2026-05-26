<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_chat_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('analysis_report_id');
            $table->foreign('analysis_report_id')
                  ->references('id')->on('analysis_reports')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->timestamps();

            $table->index('analysis_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_chat_sessions');
    }
};
