<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPreviousConsultantToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('previous_consultant_id')->nullable()->after('parent_id');
            $table->string('previous_consultant_name')->nullable()->after('previous_consultant_id');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['previous_consultant_id', 'previous_consultant_name']);
        });
    }
}
