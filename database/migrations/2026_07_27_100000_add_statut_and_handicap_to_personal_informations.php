<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatutAndHandicapToPersonalInformations extends Migration
{
    public function up()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            // Statut professionnel (optionnel) : impacte la majoration de trimestres enfants
            // (prive = MDA ; fonctionnaire = bonification). Valeurs attendues : 'prive' | 'fonctionnaire'.
            $table->string('statut_pro')->nullable();
            // Nombre d'enfants en situation de handicap (optionnel) : +8 trimestres/enfant.
            $table->unsignedTinyInteger('nombre_enfants_handicapes')->nullable();
        });
    }

    public function down()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            $table->dropColumn(['statut_pro', 'nombre_enfants_handicapes']);
        });
    }
}
