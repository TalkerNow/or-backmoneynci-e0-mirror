<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFactureIdForeignToSuiviAvancementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modifier le type de colonne facture_id pour correspondre à documents.id (BIGINT UNSIGNED) ET le rendre nullable
        DB::statement('ALTER TABLE suivi_avancement 
                       MODIFY COLUMN facture_id BIGINT UNSIGNED NULL');
        
        // Nettoyer les facture_id invalides avant d'ajouter la contrainte
        DB::statement('UPDATE suivi_avancement sa 
                       LEFT JOIN documents d ON sa.facture_id = d.id 
                       SET sa.facture_id = NULL 
                       WHERE sa.facture_id IS NOT NULL AND d.id IS NULL');
        
        // Ajouter la foreign key avec set null
        DB::statement('ALTER TABLE suivi_avancement 
                       ADD CONSTRAINT suivi_avancement_facture_id_foreign 
                       FOREIGN KEY (facture_id) REFERENCES documents(id) ON DELETE SET NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('suivi_avancement', function (Blueprint $table) {
            $table->dropForeign(['facture_id']);
        });
    }
}
