<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->index(
                ['project_id', 'sprint_id', 'workflow_id', 'position'],
                'tasks_project_sprint_workflow_position_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_project_sprint_workflow_position_idx');
        });
    }
};
