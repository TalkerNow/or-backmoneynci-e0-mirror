<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCipavToFrozenDataTable extends Migration
{
    public function up()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->json('cipav')->nullable()->after('carriere'); // array of {annee, pts_cipav_base, pts_cipav_complementaire}
        });
    }

    public function down()
    {
        Schema::table('frozen_data', function (Blueprint $table) {
            $table->dropColumn('cipav');
        });
    }
}
