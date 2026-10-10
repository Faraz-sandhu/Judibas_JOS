<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('original_estimate_minutes')->nullable()->after('due_date');
            $table->timestamp('developer_estimate_set_at')->nullable()->after('original_estimate_minutes');
        });
        Schema::create('task_estimate_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('old_minutes')->nullable();
            $table->unsignedInteger('new_minutes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_estimate_histories');
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['original_estimate_minutes', 'developer_estimate_set_at']));
    }
};
