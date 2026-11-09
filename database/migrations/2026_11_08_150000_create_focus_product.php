<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('focus_preferences', function (Blueprint $t) {
            $t->string('owner')->primary();
            $t->json('settings');
            $t->timestamps();
        });
        Schema::create('focus_projects', function (Blueprint $t) {
            $t->id();
            $t->string('owner')->index();
            $t->string('name', 120);
            $t->string('color', 7)->default('#7762ee');
            $t->boolean('is_folder')->default(false);
            $t->foreignId('folder_id')->nullable()->constrained('focus_projects')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('focus_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('owner')->index();
            $t->foreignId('project_id')->nullable()->constrained('focus_projects')->nullOnDelete();
            $t->string('title', 250);
            $t->text('notes')->nullable();
            $t->json('subtasks');
            $t->json('tags');
            $t->unsignedSmallInteger('estimate')->default(1);
            $t->string('priority')->default('none');
            $t->date('due_date')->nullable();
            $t->timestampTz('remind_at')->nullable();
            $t->timestampTz('reminded_at')->nullable();
            $t->string('repeat')->default('none');
            $t->timestampTz('completed_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('focus_sessions', function (Blueprint $t) {
            $t->id();
            $t->string('owner')->index();
            $t->string('active_owner')->nullable()->unique();
            $t->foreignId('task_id')->nullable()->constrained('focus_tasks')->nullOnDelete();
            $t->string('task_title', 250)->nullable();
            $t->string('phase');
            $t->string('mode');
            $t->string('status');
            $t->unsignedInteger('target_seconds');
            $t->unsignedInteger('elapsed_seconds')->default(0);
            $t->timestampTz('segment_started_at')->nullable();
            $t->timestampTz('started_at');
            $t->timestampTz('ended_at')->nullable();
            $t->json('segments');
            $t->boolean('manual')->default(false);
            $t->timestampsTz();
        });
        Schema::create('focus_notifications', function (Blueprint $t) {
            $t->id();
            $t->string('owner')->index();
            $t->string('title');
            $t->foreignId('task_id')->nullable()->constrained('focus_tasks')->nullOnDelete();
            $t->timestampTz('read_at')->nullable();
            $t->timestampsTz();
        });
        Schema::create('focus_groups', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->index();
            $t->string('owner');
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('focus_group_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('group_id')->constrained('focus_groups')->cascadeOnDelete();
            $t->string('owner');
            $t->unique(['group_id', 'owner']);
        });
        Schema::create('focus_rewards', function (Blueprint $t) {
            $t->id();
            $t->string('owner')->index();
            $t->string('source')->unique();
            $t->string('reason');
            $t->unsignedInteger('grams');
            $t->timestampTz('collected_at')->nullable();
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        foreach (['focus_rewards', 'focus_group_members', 'focus_groups', 'focus_notifications', 'focus_sessions', 'focus_tasks', 'focus_projects', 'focus_preferences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
