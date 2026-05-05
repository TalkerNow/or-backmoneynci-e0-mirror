<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropDateDeNaissanceFromConsultantsAccess extends Migration
{
    public function up()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->dropColumn('date_de_naissance');
        });
    }

    public function down()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->date('date_de_naissance')->nullable();
        });
    }
}
