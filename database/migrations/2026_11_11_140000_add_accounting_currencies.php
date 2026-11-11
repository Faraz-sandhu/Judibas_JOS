<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_exchange_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->string('currency', 3);
            $t->date('rate_date');
            $t->decimal('rate', 18, 6);
            $t->timestamps();
            $t->unique(['company_id', 'currency', 'rate_date']);
        });
        Schema::table('accounting_accounts', fn (Blueprint $t) => $t->string('currency', 3)->nullable());
        foreach (['accounting_journals', 'accounting_invoices', 'accounting_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('currency', 3)->nullable();
                $t->decimal('exchange_rate', 18, 6)->default(1);
            });
        }
        Schema::table('accounting_invoices', function (Blueprint $t) {
            $t->bigInteger('foreign_net_minor')->nullable();
            $t->bigInteger('foreign_tax_minor')->nullable();
            $t->bigInteger('foreign_total_minor')->nullable();
        });
        Schema::table('accounting_payments', function (Blueprint $t) {
            $t->bigInteger('foreign_amount_minor')->nullable();
            $t->bigInteger('bank_amount_minor')->nullable();
            $t->bigInteger('exchange_difference_minor')->default(0);
            $t->unsignedBigInteger('exchange_account_id')->nullable();
        });
        Schema::table('accounting_journal_lines', function (Blueprint $t) {
            $t->string('currency', 3)->nullable();
            $t->decimal('exchange_rate', 18, 6)->nullable();
            $t->bigInteger('foreign_debit_minor')->nullable();
            $t->bigInteger('foreign_credit_minor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_journal_lines', fn (Blueprint $t) => $t->dropColumn(['currency', 'exchange_rate', 'foreign_debit_minor', 'foreign_credit_minor']));
        Schema::table('accounting_payments', fn (Blueprint $t) => $t->dropColumn(['foreign_amount_minor', 'bank_amount_minor', 'exchange_difference_minor', 'exchange_account_id']));
        Schema::table('accounting_invoices', fn (Blueprint $t) => $t->dropColumn(['foreign_net_minor', 'foreign_tax_minor', 'foreign_total_minor']));
        foreach (['accounting_journals', 'accounting_invoices', 'accounting_payments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['currency', 'exchange_rate']));
        }
        Schema::table('accounting_accounts', fn (Blueprint $t) => $t->dropColumn('currency'));
        Schema::dropIfExists('accounting_exchange_rates');
    }
};
