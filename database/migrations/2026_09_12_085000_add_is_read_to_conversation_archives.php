<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversation_archives')) {
            return;
        }
        Schema::table('conversation_archives', function (Blueprint $table) {
            if (!Schema::hasColumn('conversation_archives', 'is_read')) {
                $table->boolean('is_read')->default(false)->index()->after('invisible');
            }
            if (!Schema::hasColumn('conversation_archives', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
        });

        // Reset stock: historical rows are "already seen" so badge starts at 0.
        // New chatbot conversations stay is_read=0 (unread).
        DB::table('conversation_archives')->update([
            'is_read' => 1,
            'read_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('conversation_archives')) {
            return;
        }
        Schema::table('conversation_archives', function (Blueprint $table) {
            if (Schema::hasColumn('conversation_archives', 'read_at')) {
                $table->dropColumn('read_at');
            }
            if (Schema::hasColumn('conversation_archives', 'is_read')) {
                $table->dropColumn('is_read');
            }
        });
    }
};
