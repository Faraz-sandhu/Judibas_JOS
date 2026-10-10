<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_invitations', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->change();
            $table->string('invitee_email')->nullable()->after('recipient_id')->index();
            $table->string('invitee_name')->nullable()->after('invitee_email');
        });
    }

    public function down(): void
    {
        Schema::table('pms_invitations', function (Blueprint $table) {
            $table->dropIndex(['invitee_email']);
            $table->dropColumn(['invitee_email', 'invitee_name']);
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }
};
