<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pms_task_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_task_id')->nullable()->constrained('pms_tasks')->nullOnDelete();
            $table->foreignId('destination_task_id')->nullable()->constrained('pms_tasks')->nullOnDelete();
            $table->foreignId('source_department_id')->nullable()->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('source_project_id')->nullable()->constrained('pms_projects')->nullOnDelete();
            $table->foreignId('destination_project_id')->nullable()->constrained('pms_projects')->nullOnDelete();
            $table->foreignId('handed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['source_task_id', 'destination_task_id']);
            $table->index(['destination_department_id', 'created_at']);
        });

        DB::table('pms_permissions')->updateOrInsert(['permission_key' => 'task-handoff'], [
            'permission_name' => 'Task Handoff', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('pms_permissions')->where('permission_key', 'task-handoff')->value('id');
        $roleIds = DB::table('pms_permission_roles')->whereIn('permission_id', function ($query) {
            $query->select('id')->from('pms_permissions')->where('permission_key', 'task-edit');
        })->pluck('role_id')->unique();
        foreach ($roleIds as $roleId) {
            DB::table('pms_permission_roles')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }
        Cache::forget('authorization.permission_definitions');
    }

    public function down(): void
    {
        $permissionId = DB::table('pms_permissions')->where('permission_key', 'task-handoff')->value('id');
        if ($permissionId) DB::table('pms_permission_roles')->where('permission_id', $permissionId)->delete();
        DB::table('pms_permissions')->where('permission_key', 'task-handoff')->delete();
        Schema::dropIfExists('pms_task_handoffs');
        Cache::forget('authorization.permission_definitions');
    }
};
