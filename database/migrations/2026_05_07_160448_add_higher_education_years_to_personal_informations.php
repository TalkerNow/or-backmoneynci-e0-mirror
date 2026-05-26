<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHigherEducationYearsToPersonalInformations extends Migration
{
    public function up()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            $table->unsignedTinyInteger('higher_education_years')->nullable();
        });
    }

    public function down()
    {
        Schema::table('personal_informations', function (Blueprint $table) {
            $table->dropColumn('higher_education_years');
        });
    }
}
