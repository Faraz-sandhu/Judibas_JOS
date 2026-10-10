<?php
namespace App\Modules\Pms\Database\Seeders;
use Illuminate\Database\Seeder;
class PmsFoundationSeeder extends Seeder {
 public function run():void {$this->call([PermissionSeeder::class,RoleSeeder::class,PermissionRoleSeeder::class]);}
}
