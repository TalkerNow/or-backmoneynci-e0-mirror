<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consultant_prompt_notes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('consultant_id');
            $table->foreign('consultant_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->text('content');

            $table->timestamps();

            $table->index(['client_id', 'consultant_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultant_prompt_notes');
    }
};
