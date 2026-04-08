<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRoleTypeToPromptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('prompts', 'type')) {
            DB::statement("ALTER TABLE `prompts` MODIFY `type` ENUM('general','email','rapport','analyse','autre','role') NULL");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('prompts', 'type')) {
            DB::statement("ALTER TABLE `prompts` MODIFY `type` ENUM('general','email','rapport','analyse','autre') NULL");
        }
    }
}
