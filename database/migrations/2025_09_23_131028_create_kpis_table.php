<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->date('kpi_date');
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('objet');
            $table->string('action');
            $table->index(['admin_id', 'kpi_date']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpis');
    }
};

