<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
          UserSeeder::class,
          RoleSeeder::class,
          PermissionSeeder::class,
          PermissionRoleSeeder::class,
          SkyInfinitOrganizationSeeder::class,
          RoleUserSeeder::class,
          DepartmentSeeder::class,
          UserDepartmentSeeder::class,
          GeneralProjectSeeder::class,
        ]);
    }
}
