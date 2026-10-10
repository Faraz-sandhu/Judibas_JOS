<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['is_general', 'end_date'], 'projects_general_end_idx');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status'], 'tasks_project_status_idx');
            $table->index(['completed_by', 'status', 'updated_at'], 'tasks_completed_status_updated_idx');
        });
        Schema::table('subtasks', function (Blueprint $table) {
            $table->index(['task_id', 'status'], 'subtasks_task_status_idx');
            $table->index(['completed_by', 'status', 'updated_at'], 'subtasks_completed_status_updated_idx');
        });
        Schema::table('project_user', function (Blueprint $table) {
            $table->index(['user_id', 'project_id'], 'project_user_user_project_idx');
        });
        Schema::table('task_user', function (Blueprint $table) {
            $table->index(['user_id', 'task_id'], 'task_user_user_task_idx');
        });
        Schema::table('subtask_user', function (Blueprint $table) {
            $table->index(['user_id', 'subtask_id'], 'subtask_user_user_subtask_idx');
        });
        Schema::table('timer_logs', function (Blueprint $table) {
            $table->index(['user_id', 'end_time', 'start_time'], 'timer_logs_user_end_start_idx');
            $table->index(['task_id', 'subtask_id'], 'timer_logs_task_subtask_idx');
        });
        Schema::table('invitations', function (Blueprint $table) {
            $table->index(['recipient_id', 'accepted_at', 'declined_at', 'expires_at'], 'invitations_pending_idx');
        });
        Schema::table('ch_messages', function (Blueprint $table) {
            $table->index(['to_id', 'seen'], 'ch_messages_to_seen_idx');
        });
        Schema::table('summaries', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'summaries_user_date_idx');
        });
        Schema::table('sub_task_pending_summaries', function (Blueprint $table) {
            $table->index(['user_id', 'date', 'task_id'], 'pending_summaries_user_date_task_idx');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropIndex('projects_general_end_idx'));
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_project_status_idx');
            $table->dropIndex('tasks_completed_status_updated_idx');
        });
        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropIndex('subtasks_task_status_idx');
            $table->dropIndex('subtasks_completed_status_updated_idx');
        });
        Schema::table('project_user', fn (Blueprint $table) => $table->dropIndex('project_user_user_project_idx'));
        Schema::table('task_user', fn (Blueprint $table) => $table->dropIndex('task_user_user_task_idx'));
        Schema::table('subtask_user', fn (Blueprint $table) => $table->dropIndex('subtask_user_user_subtask_idx'));
        Schema::table('timer_logs', function (Blueprint $table) {
            $table->dropIndex('timer_logs_user_end_start_idx');
            $table->dropIndex('timer_logs_task_subtask_idx');
        });
        Schema::table('invitations', fn (Blueprint $table) => $table->dropIndex('invitations_pending_idx'));
        Schema::table('ch_messages', fn (Blueprint $table) => $table->dropIndex('ch_messages_to_seen_idx'));
        Schema::table('summaries', fn (Blueprint $table) => $table->dropIndex('summaries_user_date_idx'));
        Schema::table('sub_task_pending_summaries', fn (Blueprint $table) => $table->dropIndex('pending_summaries_user_date_task_idx'));
    }
};
