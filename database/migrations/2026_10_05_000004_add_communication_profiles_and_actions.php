<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('profile_photo_path')->nullable();
        });
        Schema::table('communication_messages', function (Blueprint $t) {
            $t->boolean('forwarded')->default(false);
            $t->timestamp('pinned_at')->nullable();
            $t->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->index(['conversation_id', 'pinned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $t) {
            $t->dropIndex(['conversation_id', 'pinned_at']);
            $t->dropConstrainedForeignId('pinned_by');
            $t->dropColumn(['forwarded', 'pinned_at']);
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('profile_photo_path');
        });
    }
};
