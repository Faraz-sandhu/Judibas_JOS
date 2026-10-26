<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_invoices', function (Blueprint $t) {
            $t->json('party_details')->nullable();
            $t->string('company_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_invoices', fn (Blueprint $t) => $t->dropColumn(['party_details', 'company_name']));
    }
};
