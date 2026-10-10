<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $obsoleteIds = DB::table('pms_permissions')
            ->whereIn('permission_key', ['role-assign-user', 'permission-assign-role'])
            ->pluck('id');
        DB::table('pms_permission_roles')->whereIn('permission_id', $obsoleteIds)->delete();
        DB::table('pms_permissions')->whereIn('id', $obsoleteIds)->delete();

        DB::table('pms_permissions')->updateOrInsert(
            ['permission_key' => 'message-access'],
            ['permission_name' => 'Developer Messaging', 'created_at' => now(), 'updated_at' => now()],
        );
        $permissionId = DB::table('pms_permissions')->where('permission_key', 'message-access')->value('id');
        foreach (DB::table('pms_roles')->where('role_key', 'developer')->pluck('id') as $roleId) {
            DB::table('pms_permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('pms_permissions')->where('permission_key', 'message-access')->value('id');
        DB::table('pms_permission_roles')->where('permission_id', $permissionId)->delete();
        DB::table('pms_permissions')->where('id', $permissionId)->delete();

        foreach ([
            'role-assign-user' => 'Role Assign User',
            'permission-assign-role' => 'Permission Assign Role',
        ] as $key => $name) {
            DB::table('pms_permissions')->updateOrInsert(
                ['permission_key' => $key],
                ['permission_name' => $name, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
