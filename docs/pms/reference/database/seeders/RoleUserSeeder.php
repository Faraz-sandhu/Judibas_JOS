<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@skyinfinit.com')->firstOrFail();
        $role = Role::where('role_key', 'admin')->firstOrFail();

        DB::table('role_users')->updateOrInsert(
            ['role_id' => $role->id, 'user_id' => $user->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
