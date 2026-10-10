<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branding_settings', function (Blueprint $table) {
            $table->string('sidebar_logo_text_dark')->nullable()->after('sidebar_logo_text');
            $table->string('login_logo_dark')->nullable()->after('login_logo');
            $table->string('login_cover_dark')->nullable()->after('login_cover');
        });
    }

    public function down(): void
    {
        Schema::table('branding_settings', fn (Blueprint $table) => $table->dropColumn([
            'sidebar_logo_text_dark', 'login_logo_dark', 'login_cover_dark',
        ]));
    }
};
