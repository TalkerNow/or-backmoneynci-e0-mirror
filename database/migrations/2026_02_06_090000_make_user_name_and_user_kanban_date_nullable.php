<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeUserNameAndUserKanbanDateNullable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // users.name -> nullable
        if (Schema::hasColumn('users', 'name')) {
            DB::statement("ALTER TABLE `users` MODIFY `name` VARCHAR(255) NULL");
        }

        // user_kanbans.date -> nullable
        if (Schema::hasColumn('user_kanbans', 'date')) {
            DB::statement("ALTER TABLE `user_kanbans` MODIFY `date` DATETIME NULL");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // users.name -> not null
        if (Schema::hasColumn('users', 'name')) {
            DB::statement("ALTER TABLE `users` MODIFY `name` VARCHAR(255) NOT NULL");
        }

        // user_kanbans.date -> not null
        if (Schema::hasColumn('user_kanbans', 'date')) {
            DB::statement("ALTER TABLE `user_kanbans` MODIFY `date` DATETIME NOT NULL");
        }
    }
}
