<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', fn (Blueprint $t) => $t->string('code', 255)->change());
    }

    public function down(): void
    {
        // Preserve nested account codes rather than truncating identifiers on rollback.
    }
};
