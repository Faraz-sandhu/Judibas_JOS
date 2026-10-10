<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pms_project_chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pms_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // sender
            $table->text('message');
            $table->foreignId('parent_id')->nullable()->constrained('pms_project_chats')->onDelete('cascade'); // reply to
            $table->json('seen_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_project_chats');
    }
};
