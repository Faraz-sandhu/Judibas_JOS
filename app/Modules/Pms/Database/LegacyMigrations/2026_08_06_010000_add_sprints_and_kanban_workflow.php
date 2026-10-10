<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pms_projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['planned', 'active', 'completed'])->default('planned');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->foreignId('sprint_id')->nullable()->after('project_id')->constrained('pms_sprints')->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('status');
            $table->index(['sprint_id', 'status', 'position'], 'tasks_sprint_status_position_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending','in_progress','in_review','completed') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('pms_tasks')->where('status', 'in_review')->update(['status' => 'in_progress']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tasks MODIFY status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending'");
        }
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_sprint_status_position_idx');
            $table->dropConstrainedForeignId('sprint_id');
            $table->dropColumn('position');
        });
        Schema::dropIfExists('pms_sprints');
    }
};
