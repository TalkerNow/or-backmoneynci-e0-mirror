<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateUserPermissionsTable extends Migration
{
    /**
     * Remplace la whitelist d'IDs codée en dur ([4, 1271, 1638]) pour
     * "Accès Consultant" et "Admin Moteur" par des permissions en base.
     */
    private const LEGACY_ADMIN_IDS = [4, 1271, 1638];
    private const LEGACY_PERMISSIONS = ['consultant-access', 'admin-moteur'];

    public function up()
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('permission');
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        $now = now();
        foreach (self::LEGACY_ADMIN_IDS as $userId) {
            if (!DB::table('users')->where('id', $userId)->exists()) {
                continue;
            }
            foreach (self::LEGACY_PERMISSIONS as $permission) {
                DB::table('user_permissions')->insertOrIgnore([
                    'user_id'    => $userId,
                    'permission' => $permission,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_permissions');
    }
}
