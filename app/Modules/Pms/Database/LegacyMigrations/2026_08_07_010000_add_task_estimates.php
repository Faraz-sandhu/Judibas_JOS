<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_tasks', function (Blueprint $table) {
            $table->unsignedInteger('original_estimate_minutes')->nullable()->after('due_date');
            $table->timestamp('developer_estimate_set_at')->nullable()->after('original_estimate_minutes');
        });
        Schema::create('pms_task_estimate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pms_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('old_minutes')->nullable();
            $table->unsignedInteger('new_minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_task_estimate_histories');
        Schema::table('pms_tasks', fn (Blueprint $table) => $table->dropColumn(['original_estimate_minutes', 'developer_estimate_set_at']));
    }
};
