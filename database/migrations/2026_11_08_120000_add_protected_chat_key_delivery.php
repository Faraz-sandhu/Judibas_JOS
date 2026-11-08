<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('communication_protection_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('public_key');
            $t->timestamps();
            $t->index('user_id');
        });
        Schema::create('communication_protection_recovery', function (Blueprint $t) {
            $t->unsignedBigInteger('conversation_id')->primary();
            $t->foreign('conversation_id')->references('id')->on('communication_conversations')->cascadeOnDelete();
            $t->uuid('key_id');
            $t->text('envelope');
            $t->timestamps();
        });
        Schema::create('communication_key_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->uuid('device_id');
            $t->foreign('device_id')->references('id')->on('communication_protection_devices')->cascadeOnDelete();
            $t->string('status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['conversation_id','device_id']);
        });
        Schema::create('communication_key_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->uuid('device_id');
            $t->foreign('device_id')->references('id')->on('communication_protection_devices')->cascadeOnDelete();
            $t->uuid('key_id');
            $t->text('envelope')->nullable();
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id','device_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('communication_key_deliveries');
        Schema::dropIfExists('communication_key_requests');
        Schema::dropIfExists('communication_protection_recovery');
        Schema::dropIfExists('communication_protection_devices');
    }
};
