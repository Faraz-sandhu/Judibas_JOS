<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_e2ee_threads', function (Blueprint $t) {
            $t->uuid('peer_device_id')->nullable()->change();
            $t->unique(['owner_device_id', 'peer_user_id'], 'communication_e2ee_owner_peer_unique');
        });
        Schema::create('communication_e2ee_signals', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('thread_id');
            $t->uuid('sender_device_id');
            $t->jsonb('envelopes');
            $t->timestamp('expires_at')->index();
            $t->timestamp('created_at');
            $t->foreign('thread_id')->references('id')->on('communication_e2ee_threads')->restrictOnDelete();
            $t->foreign('sender_device_id')->references('id')->on('communication_e2ee_devices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('communication_e2ee_threads')->whereNull('peer_device_id')->exists()) {
            throw new RuntimeException('Pending encrypted invitations must be resolved before rollback.');
        }Schema::dropIfExists('communication_e2ee_signals');
        Schema::table('communication_e2ee_threads', function (Blueprint $t) {
            $t->dropUnique('communication_e2ee_owner_peer_unique');
            $t->uuid('peer_device_id')->nullable(false)->change();
        });
    }
};
