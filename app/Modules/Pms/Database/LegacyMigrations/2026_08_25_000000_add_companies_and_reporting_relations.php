<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website', 2048)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['status', 'name']);
        });

        Schema::table('pms_projects', function (Blueprint $table) {
            // Nullable/nullOnDelete keeps every existing live project valid.
            $table->foreignId('company_id')->nullable()->after('id')
                ->constrained('pms_companies')->nullOnDelete();
            $table->index(['company_id', 'start_date', 'end_date'], 'projects_company_period_idx');
        });

        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->index(['department_id', 'project_id', 'created_at'], 'tasks_department_project_created_idx');
            $table->index(['department_id', 'due_date', 'status'], 'tasks_department_due_status_idx');
        });

        Schema::table('pms_timer_logs', function (Blueprint $table) {
            $table->index(['task_id', 'user_id', 'start_time', 'end_time'], 'timer_task_user_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pms_timer_logs', fn (Blueprint $table) => $table->dropIndex('timer_task_user_period_idx'));
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_department_project_created_idx');
            $table->dropIndex('tasks_department_due_status_idx');
        });
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->dropIndex('projects_company_period_idx');
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('pms_companies');
    }
};
