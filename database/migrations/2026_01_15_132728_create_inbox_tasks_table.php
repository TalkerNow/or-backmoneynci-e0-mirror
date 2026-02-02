<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_tasks', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();

            $table->date('date')->nullable();
            $table->json('data')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('admin_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_tasks');
    }
};

