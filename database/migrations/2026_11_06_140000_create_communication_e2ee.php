<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_e2ee_devices', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('principal', 50)->unique();
            $t->string('public_key', 100);
            $t->timestamp('created_at');
        });
        Schema::create('communication_e2ee_threads', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('owner_device_id');
            $t->uuid('peer_device_id');
            $t->foreignId('peer_user_id')->constrained('users')->restrictOnDelete();
            $t->foreign('owner_device_id')->references('id')->on('communication_e2ee_devices')->restrictOnDelete();
            $t->foreign('peer_device_id')->references('id')->on('communication_e2ee_devices')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['owner_device_id', 'peer_device_id']);
        });
        Schema::create('communication_e2ee_messages', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('thread_id');
            $t->uuid('sender_device_id');
            $t->jsonb('envelopes');
            $t->timestamp('created_at');
            $t->foreign('thread_id')->references('id')->on('communication_e2ee_threads')->restrictOnDelete();
            $t->foreign('sender_device_id')->references('id')->on('communication_e2ee_devices')->restrictOnDelete();
            $t->index(['thread_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_e2ee_messages');
        Schema::dropIfExists('communication_e2ee_threads');
        Schema::dropIfExists('communication_e2ee_devices');
    }
};
