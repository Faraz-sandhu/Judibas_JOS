<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table) {
            $table->boolean('notification_emails_enabled')->default(true)->after('from_name');
            $table->boolean('invitation_emails_enabled')->default(true)->after('notification_emails_enabled');
            $table->unsignedInteger('daily_limit')->default(300)->after('invitation_emails_enabled');
            $table->unsignedInteger('warning_threshold')->default(270)->after('daily_limit');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->string('delivery_status', 40)->default('pending')->after('expires_at')->index();
            $table->timestamp('emailed_at')->nullable()->after('delivery_status');
            $table->text('email_error')->nullable()->after('emailed_at');
        });

        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('recipient');
            $table->foreignId('invitation_id')->nullable()->constrained('invitations')->nullOnDelete();
            $table->string('status', 40)->index();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });
        Cache::forget('mail.settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::table('invitations', fn (Blueprint $table) => $table->dropColumn(['delivery_status', 'emailed_at', 'email_error']));
        Schema::table('mail_settings', fn (Blueprint $table) => $table->dropColumn([
            'notification_emails_enabled', 'invitation_emails_enabled', 'daily_limit', 'warning_threshold',
        ]));
        Cache::forget('mail.settings');
    }
};
