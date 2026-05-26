<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class MakeDateDeNaissanceNullableInConsultantsAccess extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE consultants_access MODIFY date_de_naissance DATE NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE consultants_access MODIFY date_de_naissance DATE NOT NULL');
    }
}
