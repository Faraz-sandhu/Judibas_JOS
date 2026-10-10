<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->index(['is_general', 'status'], 'projects_general_status_idx');
        });
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->index(['project_id', 'due_date'], 'tasks_project_due_idx');
        });
        Schema::table('pms_role_users', function (Blueprint $table) {
            $table->index(['role_id', 'user_id'], 'role_users_role_user_idx');
            $table->index(['user_id', 'role_id'], 'role_users_user_role_idx');
        });
        Schema::table('pms_user_departments', function (Blueprint $table) {
            $table->index(['dept_id', 'user_id'], 'user_departments_dept_user_idx');
            $table->index(['user_id', 'dept_id'], 'user_departments_user_dept_idx');
        });
        Schema::table('pms_task_user', function (Blueprint $table) {
            $table->index(['task_id', 'user_id'], 'task_user_task_user_idx');
        });
        Schema::table('pms_subtask_user', function (Blueprint $table) {
            $table->index(['subtask_id', 'user_id'], 'subtask_user_subtask_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pms_projects', fn (Blueprint $table) => $table->dropIndex('projects_general_status_idx'));
        Schema::table('pms_tasks', fn (Blueprint $table) => $table->dropIndex('tasks_project_due_idx'));
        Schema::table('pms_role_users', function (Blueprint $table) {
            $table->dropIndex('role_users_role_user_idx');
            $table->dropIndex('role_users_user_role_idx');
        });
        Schema::table('pms_user_departments', function (Blueprint $table) {
            $table->dropIndex('user_departments_dept_user_idx');
            $table->dropIndex('user_departments_user_dept_idx');
        });
        Schema::table('pms_task_user', fn (Blueprint $table) => $table->dropIndex('task_user_task_user_idx'));
        Schema::table('pms_subtask_user', fn (Blueprint $table) => $table->dropIndex('subtask_user_subtask_user_idx'));
    }
};
