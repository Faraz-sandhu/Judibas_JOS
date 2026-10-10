<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_notifications', function (Blueprint $table) {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_user_read_created_idx'
            );
        });

        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->index(
                ['project_id', 'sprint_id', 'status', 'position'],
                'tasks_board_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pms_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_read_created_idx');
        });
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_board_lookup_idx');
        });
    }
};
