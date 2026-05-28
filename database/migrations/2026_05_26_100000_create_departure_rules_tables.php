<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartureRulesTables extends Migration
{
    public function up(): void
    {
        Schema::create('departure_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('key_max')->nullable();
            $table->smallInteger('age_months');
            $table->smallInteger('trim');
            $table->boolean('is_default')->default(false);
            $table->tinyInteger('sort_order');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('departure_rules_history', function (Blueprint $table) {
            $table->id();
            $table->json('rules_json');
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departure_rules_history');
        Schema::dropIfExists('departure_rules');
    }
}
