<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("inbound_emails", function (Blueprint $table) {
            $table->id();
            $table->string("gmail_message_id", 255)->unique();
            $table->string("gmail_thread_id", 255)->nullable()->index();
            $table->string("source", 32)->default("other")->index(); // cf7|chatbot_report|other
            $table->string("from_email", 255)->nullable()->index();
            $table->string("from_name", 255)->nullable();
            $table->string("to_email", 255)->nullable()->index();
            $table->string("subject", 512)->nullable();
            $table->text("snippet")->nullable();
            $table->timestamp("received_at")->nullable()->index();
            $table->string("gmail_permalink", 1024)->nullable();
            $table->timestamps();
        });

        // Diags: widen list_unsubscribed varchar(255) → JSON for Brevo arrays (raw ALTER, no dbal)
        if (Schema::hasTable("simulator_difficulty_results")
            && Schema::hasColumn("simulator_difficulty_results", "list_unsubscribed")) {
            DB::statement("ALTER TABLE simulator_difficulty_results MODIFY list_unsubscribed JSON NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists("inbound_emails");

        if (Schema::hasTable("simulator_difficulty_results")
            && Schema::hasColumn("simulator_difficulty_results", "list_unsubscribed")) {
            DB::statement("ALTER TABLE simulator_difficulty_results MODIFY list_unsubscribed VARCHAR(255) NULL");
        }
    }
};
