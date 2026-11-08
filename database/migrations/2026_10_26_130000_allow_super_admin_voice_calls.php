<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A null participant is the separate Super Admin identity, never an employee.
        Schema::table('communication_calls', function (Blueprint $t) {
            $t->unsignedBigInteger('caller_id')->nullable()->change();
            $t->unsignedBigInteger('callee_id')->nullable()->change();
        });
        if (DB::getDriverName() === 'pgsql') DB::statement('ALTER TABLE communication_calls ADD CONSTRAINT communication_distinct_call_participants CHECK ((caller_id IS NOT NULL OR callee_id IS NOT NULL) AND (caller_id IS NULL OR callee_id IS NULL OR caller_id <> callee_id))');
    }

    public function down(): void
    {
        // Retain call history: rolling back is only possible before Super Admin calls exist.
        if (DB::table('communication_calls')->whereNull('caller_id')->orWhereNull('callee_id')->exists()) throw new RuntimeException('Super Admin call history exists; this migration cannot be reversed without losing history.');
        if (DB::getDriverName() === 'pgsql') DB::statement('ALTER TABLE communication_calls DROP CONSTRAINT communication_distinct_call_participants');
        Schema::table('communication_calls', function (Blueprint $t) {
            $t->unsignedBigInteger('caller_id')->nullable(false)->change();
            $t->unsignedBigInteger('callee_id')->nullable(false)->change();
        });
    }
};
