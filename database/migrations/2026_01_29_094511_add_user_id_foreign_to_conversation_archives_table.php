<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUserIdForeignToConversationArchivesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conversation_archives', function (Blueprint $table) {
            // Ajouter la colonne user_id si elle n'existe pas
            if (!Schema::hasColumn('conversation_archives', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Just skip the rollback for now since foreign key may not exist
        // This is a safe operation since the table creation is in a different migration
    }
}
