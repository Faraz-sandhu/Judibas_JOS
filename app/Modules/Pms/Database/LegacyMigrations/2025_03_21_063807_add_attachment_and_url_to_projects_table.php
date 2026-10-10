<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('image');
            $table->string('url')->nullable()->after('attachment');
            $table->string('status')->default('pending')->after('url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_projects', function (Blueprint $table) {
            $table->dropColumn(['attachment', 'url','status']);
        });
    }
};
