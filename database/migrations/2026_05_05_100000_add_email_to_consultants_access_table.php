<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailToConsultantsAccessTable extends Migration
{
    public function up()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('consultants_access', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
}
