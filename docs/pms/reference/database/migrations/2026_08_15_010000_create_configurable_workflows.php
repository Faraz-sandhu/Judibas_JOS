<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['department_id', 'is_active']);
        });

        Schema::create('workflow_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 30)->default('secondary');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->index(['workflow_id', 'position']);
        });

        Schema::create('project_workflow', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'workflow_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('project_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('workflow_column_id')->nullable()->after('workflow_id')->constrained('workflow_columns')->nullOnDelete();
            $table->index(['workflow_id', 'workflow_column_id', 'position'], 'tasks_workflow_column_position_idx');
        });

        $now = now();
        $workflowId = DB::table('workflows')->insertGetId([
            'name' => 'Standard Workflow',
            'description' => 'Default workflow migrated from the original task statuses.',
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $columnIds = [];
        foreach ([
            ['pending', 'To Do', 'secondary', true, false],
            ['in_progress', 'In Progress', 'primary', false, false],
            ['in_review', 'In Review', 'warning', false, false],
            ['completed', 'Done', 'success', false, true],
        ] as $position => [$status, $name, $color, $initial, $completed]) {
            $columnIds[$status] = DB::table('workflow_columns')->insertGetId([
                'workflow_id' => $workflowId,
                'name' => $name,
                'color' => $color,
                'position' => $position,
                'is_initial' => $initial,
                'is_completed' => $completed,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('projects')->pluck('id')->each(fn ($projectId) => DB::table('project_workflow')->insertOrIgnore([
            'project_id' => $projectId,
            'workflow_id' => $workflowId,
        ]));
        foreach ($columnIds as $status => $columnId) {
            DB::table('tasks')->where('status', $status)->update([
                'workflow_id' => $workflowId,
                'workflow_column_id' => $columnId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['workflow_column_id']);
            $table->dropForeign(['workflow_id']);
            $table->dropForeign(['department_id']);
            $table->dropIndex('tasks_workflow_column_position_idx');
            $table->dropColumn(['workflow_column_id', 'workflow_id', 'department_id']);
        });
        Schema::dropIfExists('project_workflow');
        Schema::dropIfExists('workflow_columns');
        Schema::dropIfExists('workflows');
    }
};
