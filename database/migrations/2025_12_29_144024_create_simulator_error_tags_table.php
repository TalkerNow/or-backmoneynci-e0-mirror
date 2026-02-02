<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulator_error_tags', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();      // = client_id
            $table->unsignedBigInteger('document_id')->nullable();

            $table->text('error_handling')->nullable();
            $table->json('tag')->nullable(); // liste de tags

            $table->timestamps();

            // utile pour tes GET par client / document
            $table->index('user_id');
            $table->index('document_id');
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulator_error_tags');
    }
};


