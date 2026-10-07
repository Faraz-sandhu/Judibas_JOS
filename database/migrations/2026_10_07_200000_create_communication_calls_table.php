<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_calls', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('callee_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 10);
            $t->string('status', 15)->default('ringing');
            $t->timestamp('answered_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamp('caller_seen_at');
            $t->timestamp('callee_seen_at');
            $t->timestamps();
            $t->index(['caller_id', 'created_at']);
            $t->index(['callee_id', 'created_at']);
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_calls');
    }
};
