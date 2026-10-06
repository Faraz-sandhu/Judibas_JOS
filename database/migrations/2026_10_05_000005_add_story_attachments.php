<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_stories', function (Blueprint $t) {
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->string('attachment_mime', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_stories', function (Blueprint $t) {
            $t->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};
