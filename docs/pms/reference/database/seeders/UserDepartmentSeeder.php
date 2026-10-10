<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@skyinfinit.com')->firstOrFail();
        $department = Department::where('dept_name', 'Admin')->firstOrFail();

        DB::table('user_departments')->updateOrInsert(
            ['user_id' => $user->id, 'dept_id' => $department->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
}
