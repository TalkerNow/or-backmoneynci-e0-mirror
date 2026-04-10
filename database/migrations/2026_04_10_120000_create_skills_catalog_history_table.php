<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSkillsCatalogHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('skills_catalog_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('skills_catalog_id');
            $table->integer('version');
            $table->longText('skill_md');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at');

            $table->foreign('skills_catalog_id')
                  ->references('id')
                  ->on('skills_catalog')
                  ->onDelete('cascade');

            $table->foreign('created_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index(['skills_catalog_id', 'version']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('skills_catalog_history');
    }
}
