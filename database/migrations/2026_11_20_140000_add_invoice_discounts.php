<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_invoices', function (Blueprint $t) {
            $t->string('discount_type', 10)->default('fixed');
            $t->string('discount_value', 30)->default('0.00');
            $t->bigInteger('discount_minor')->default(0);
            $t->bigInteger('foreign_discount_minor')->default(0);
            $t->bigInteger('subtotal_minor')->nullable();
            $t->bigInteger('foreign_subtotal_minor')->nullable();
        });
    }

    public function down(): void
    { /* Keep historical invoice discount amounts. */
    }
};
