<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_archives', function (Blueprint $table) {
            $table->id();

            // Résumé de la conversation
            $table->text('summary')->nullable();

            // Liste complète des messages
            $table->json('messages');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_archives');
    }
};

