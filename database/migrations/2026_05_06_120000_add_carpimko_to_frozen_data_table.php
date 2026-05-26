<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCarpimkoToFrozenDataTable extends Migration
{
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->json('carpimko')->nullable()->after('cipav'); // array of {annee, points_base, points_asv, points_complementaire}
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->dropColumn('carpimko');
        });
    }
}
