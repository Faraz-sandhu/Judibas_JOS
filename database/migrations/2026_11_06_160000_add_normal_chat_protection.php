<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('communication_conversations', function (Blueprint $t) {
            $t->text('protection')->nullable();
        });
        Schema::table('communication_messages', function (Blueprint $t) {
            $t->text('protected_payload')->nullable();
            $t->uuid('protected_uuid')->nullable()->unique();
        });
    }
    public function down(): void
    {
        Schema::table('communication_messages', fn (Blueprint $t) => $t->dropColumn(['protected_payload', 'protected_uuid']));
        Schema::table('communication_conversations', fn (Blueprint $t) => $t->dropColumn('protection'));
    }
};
