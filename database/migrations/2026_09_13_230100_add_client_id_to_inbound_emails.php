<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn("inbound_emails", "client_id")) {
            Schema::table("inbound_emails", function (Blueprint $table) {
                $table->unsignedBigInteger("client_id")->nullable()->index()->after("source");
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn("inbound_emails", "client_id")) {
            Schema::table("inbound_emails", function (Blueprint $table) {
                $table->dropColumn("client_id");
            });
        }
    }
};
