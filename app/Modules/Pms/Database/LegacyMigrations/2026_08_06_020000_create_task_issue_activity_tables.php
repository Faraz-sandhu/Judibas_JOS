<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pms_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('pms_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('pms_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_task_attachments');
        Schema::dropIfExists('pms_task_comments');
    }
};
