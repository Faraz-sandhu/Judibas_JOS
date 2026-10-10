<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'project_id'], 'department_project_unique');
            $table->index(['project_id', 'department_id'], 'department_project_project_idx');
        });

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'department_id')) {
            DB::table('department_project')->insertUsing(
                ['department_id', 'project_id'],
                DB::table('tasks')->whereNotNull('department_id')
                    ->select(['department_id', 'project_id'])->distinct()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_project');
    }
};
