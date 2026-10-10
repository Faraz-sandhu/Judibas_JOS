<?php

namespace App\Modules\Pms\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Pms\Models\Role;

class RoleSeeder extends Seeder
{
  public function run()
  {
    $roles = [
      ['role_name' => 'Admin', 'role_key' => 'admin'],
      ['role_name' => 'Development Team Lead', 'role_key' => 'team_leader'],
      ['role_name' => 'Developer', 'role_key' => 'developer'],
      ['role_name' => 'Project Manager', 'role_key' => 'project_manager'],
      ['role_name' => 'Graphic Team Lead', 'role_key' => 'team_leader'],
      ['role_name' => 'Graphic', 'role_key' => 'graphic'],
      ['role_name' => 'SEO Team Lead', 'role_key' => 'team_leader'],
      ['role_name' => 'SEO', 'role_key' => 'employee'],
      ['role_name' => 'Writer Team Lead', 'role_key' => 'team_leader'],
      ['role_name' => 'Writer', 'role_key' => 'writer'],
    ];

    foreach ($roles as $role) {
      Role::updateOrCreate(['role_name' => $role['role_name'], 'role_key' => $role['role_key']], $role);
    }
  }
}
