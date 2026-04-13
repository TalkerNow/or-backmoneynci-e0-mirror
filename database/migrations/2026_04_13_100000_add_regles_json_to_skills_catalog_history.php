<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReglesJsonToSkillsCatalogHistory extends Migration
{
    public function up()
    {
        Schema::table('skills_catalog_history', function (Blueprint $table) {
            $table->json('regles_json')->nullable()->after('skill_md');
        });
    }

    public function down()
    {
        Schema::table('skills_catalog_history', function (Blueprint $table) {
            $table->dropColumn('regles_json');
        });
    }
}
