<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });
        foreach ([
            ['Department Task View All', 'department-task-view-all'],
            ['Department Task View Assigned', 'department-task-view-assigned'],
        ] as [$name, $key]) {
            DB::table('permissions')->updateOrInsert(['permission_key' => $key], ['permission_name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
        $allId = DB::table('permissions')->where('permission_key', 'department-task-view-all')->value('id');
        $assignedId = DB::table('permissions')->where('permission_key', 'department-task-view-assigned')->value('id');
        foreach (DB::table('roles')->pluck('id') as $roleId) DB::table('permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $assignedId]);
        foreach (DB::table('roles')->whereIn('role_key', ['admin','project_manager','team_leader'])->pluck('id') as $roleId) DB::table('permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $allId]);
        Cache::forget('authorization.permission_definitions');
    }

    public function down(): void
    {
        foreach (['department-task-view-all','department-task-view-assigned'] as $key) {
            $id = DB::table('permissions')->where('permission_key', $key)->value('id');
            if ($id) DB::table('permission_roles')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
        Cache::forget('authorization.permission_definitions');
    }
};
