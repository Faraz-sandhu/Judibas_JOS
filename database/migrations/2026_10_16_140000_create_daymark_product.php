<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daymark_departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->cascadeOnDelete();
            $t->string('name', 200);
            $t->timestamps();
            $t->unique(['company_id', 'name']);
        });
        Schema::create('daymark_custom_roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('base_role');
            $t->json('permissions');
            $t->timestamps();
            $t->unique(['company_id', 'name']);
        });
        Schema::create('daymark_members', function (Blueprint $t) {
            $t->unsignedBigInteger('id')->primary();
            $t->foreign('id')->references('id')->on('users')->cascadeOnDelete();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->string('department', 200)->default('');
            $t->string('designation', 200)->default('');
            $t->string('role')->default('employee');
            $t->foreignId('custom_role_id')->nullable()->constrained('daymark_custom_roles')->nullOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('daymark_assignments', function (Blueprint $t) {
            $t->foreignId('manager_id')->constrained('daymark_members')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('daymark_members')->cascadeOnDelete();
            $t->primary(['manager_id', 'employee_id']);
        });
        Schema::create('daymark_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('daymark_members');
            $t->date('date');
            foreach (['completed', 'progress', 'plan', 'blockers'] as $field) {
                $t->text($field);
            }$t->string('content_format')->default('plain');
            $t->decimal('hours', 4, 2)->default(0);
            $t->string('status')->default('draft');
            $t->text('feedback')->nullable();
            $t->unsignedBigInteger('reviewer_id')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'date']);
            $t->index(['date', 'status']);
        });
        Schema::create('daymark_audits', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('name');
            $t->string('action');
            $t->text('target');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['daymark_audits', 'daymark_reports', 'daymark_assignments', 'daymark_members', 'daymark_custom_roles', 'daymark_departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
