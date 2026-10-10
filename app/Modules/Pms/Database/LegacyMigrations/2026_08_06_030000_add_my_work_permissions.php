<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('pms_permissions')->updateOrInsert(
            ['permission_key' => 'my-work-view'],
            ['permission_name' => 'My Work View', 'created_at' => $now, 'updated_at' => $now],
        );
        DB::table('pms_permissions')->updateOrInsert(
            ['permission_key' => 'my-work-manage-team'],
            ['permission_name' => 'My Work Team Oversight', 'created_at' => $now, 'updated_at' => $now],
        );

        $viewPermission = DB::table('pms_permissions')->where('permission_key', 'my-work-view')->value('id');
        $managePermission = DB::table('pms_permissions')->where('permission_key', 'my-work-manage-team')->value('id');

        foreach (DB::table('pms_roles')->pluck('id') as $roleId) {
            DB::table('pms_permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $viewPermission]);
        }
        foreach (DB::table('pms_roles')->whereIn('role_key', ['admin', 'project_manager', 'team_leader'])->pluck('id') as $roleId) {
            DB::table('pms_permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $managePermission]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('pms_permissions')->whereIn('permission_key', ['my-work-view', 'my-work-manage-team'])->pluck('id');
        DB::table('pms_permission_roles')->whereIn('permission_id', $ids)->delete();
        DB::table('pms_permissions')->whereIn('id', $ids)->delete();
    }
};
