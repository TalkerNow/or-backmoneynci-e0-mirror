<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReplaceNomPrenomWithNameInConsultantsAccess extends Migration
{
    public function up()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->dropColumn(['nom', 'prenom']);
            $table->string('name', 200)->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('nom', 100)->default('');
            $table->string('prenom', 100)->default('');
        });
    }
}
