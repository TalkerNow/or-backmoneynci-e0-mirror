<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsultantsAccessSeeder extends Seeder
{
    public function run()
    {
        DB::table('consultants_access')->truncate();

        $consultants = DB::table('users')
            ->where('role', 'Consultant')
            ->select(['id as user_id', 'name', 'email'])
            ->get();

        $now = now();

        foreach ($consultants as $c) {
            DB::table('consultants_access')->insert([
                'user_id'              => $c->user_id,
                'email'                => $c->email ?? null,
                'name'                 => $c->name ?? '',
                'access_type'          => 'credits',
                'remaining_credits'    => 0,
                'pass_expiration_date' => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }
}
