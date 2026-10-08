<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_calls', function (Blueprint $t) {
            $t->timestamp('callee_read_at')->nullable();
            $t->index(['callee_id', 'callee_read_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('communication_calls', function (Blueprint $t) {
            $t->dropIndex(['callee_id', 'callee_read_at', 'status']);
            $t->dropColumn('callee_read_at');
        });
    }
};
