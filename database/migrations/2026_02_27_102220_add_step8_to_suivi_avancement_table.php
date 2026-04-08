<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('suivi_avancement', function (Blueprint $table) {
            // Add step 8 to accommodate the frontend mapping for "Envoi du contrat"
            $table->timestamp('step8_completed_at')->nullable()->after('step7_completed_at');
        });
    }

    public function down()
    {
        Schema::table('suivi_avancement', function (Blueprint $table) {
            $table->dropColumn('step8_completed_at');
        });
    }
};
