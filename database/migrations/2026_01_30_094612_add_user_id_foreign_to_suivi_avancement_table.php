<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUserIdForeignToSuiviAvancementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Nettoyer les client_id invalides avant d'ajouter la contrainte
        DB::statement('UPDATE suivi_avancement sa 
                       LEFT JOIN users u ON sa.client_id = u.id 
                       SET sa.client_id = NULL 
                       WHERE sa.client_id IS NOT NULL AND u.id IS NULL');
        
        // Modifier le type de colonne client_id pour correspondre à users.id (BIGINT UNSIGNED)
        DB::statement('ALTER TABLE suivi_avancement 
                       MODIFY COLUMN client_id BIGINT UNSIGNED NULL');
        
        // Ajouter la foreign key avec set null
        DB::statement('ALTER TABLE suivi_avancement 
                       ADD CONSTRAINT suivi_avancement_client_id_foreign 
                       FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('suivi_avancement', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });
    }
}
