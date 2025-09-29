<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePersonalInformationAddBirthPlace extends Migration
{
    public function up()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            $table->string('birth_place')->nullable();
        });
    }

    public function down()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            $table->dropColumn('birth_place');
        });
    }
}
