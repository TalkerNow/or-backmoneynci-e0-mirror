<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inbound_emails')) {
            return;
        }
        Schema::table('inbound_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_emails', 'is_read')) {
                $table->boolean('is_read')->default(false)->index();
            }
            if (!Schema::hasColumn('inbound_emails', 'read_at')) {
                $table->timestamp('read_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inbound_emails')) {
            return;
        }
        Schema::table('inbound_emails', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_emails', 'read_at')) {
                $table->dropColumn('read_at');
            }
            if (Schema::hasColumn('inbound_emails', 'is_read')) {
                $table->dropColumn('is_read');
            }
        });
    }
};
