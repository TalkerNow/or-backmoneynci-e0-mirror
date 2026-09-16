<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_archives', function (Blueprint $table) {
            // 'eor' = chatbot historique eor.fr ; 'expert-retraite' = chatbot expert-retraite.com
            $table->string('source', 32)->default('eor')->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_archives', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
