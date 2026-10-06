<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_teams', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->timestamps();
        });
        Schema::create('communication_team_members', function (Blueprint $t) {
            $t->foreignId('team_id')->constrained('communication_teams')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['team_id', 'user_id']);
        });
        Schema::create('communication_conversations', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12);
            $t->string('name', 100)->nullable();
            $t->foreignId('team_id')->nullable()->constrained('communication_teams')->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('direct_key')->nullable()->unique();
            $t->timestamps();
        });
        Schema::create('communication_members', function (Blueprint $t) {
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->unique(['conversation_id', 'user_id']);
        });
        Schema::create('communication_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('sender_name', 100);
            $t->text('body')->nullable();
            $t->foreignId('reply_to')->nullable()->constrained('communication_messages')->nullOnDelete();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->timestamp('edited_at')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });
        Schema::create('communication_stories', function (Blueprint $t) {
            $t->id();
            $t->string('title', 100);
            $t->text('body');
            $t->timestamp('expires_at');
            $t->timestamps();
        });
        Schema::create('communication_audits', function (Blueprint $t) {
            $t->id();
            $t->string('admin_email');
            $t->foreignId('viewed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('conversation_id')->nullable()->constrained('communication_conversations')->nullOnDelete();
            $t->string('action', 50);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['communication_audits', 'communication_stories', 'communication_messages', 'communication_members', 'communication_conversations', 'communication_team_members', 'communication_teams'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
