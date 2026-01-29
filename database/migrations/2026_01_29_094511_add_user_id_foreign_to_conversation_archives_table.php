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
        // Nettoyer les user_id invalides avant d'ajouter la contrainte
        DB::statement('UPDATE conversation_archives ca 
                       LEFT JOIN users u ON ca.user_id = u.id 
                       SET ca.user_id = NULL 
                       WHERE ca.user_id IS NOT NULL AND u.id IS NULL');
        
        Schema::table('conversation_archives', function (Blueprint $table) {
            // S'assurer que user_id est nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();
            
            // Ajouter la foreign key avec set null
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conversation_archives', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
}
