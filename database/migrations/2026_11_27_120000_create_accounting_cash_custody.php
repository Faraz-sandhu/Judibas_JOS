<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_cash_custodians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('account_id')->unique()->constrained('accounting_accounts');
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
        });
        Schema::create('accounting_cash_custody_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->foreignId('journal_id')->unique()->constrained('accounting_journals');
            $t->foreignId('custodian_id')->constrained('accounting_cash_custodians');
            $t->string('direction', 20);
            $t->foreignId('source_account_id')->constrained('accounting_accounts');
            $t->foreignId('destination_account_id')->constrained('accounting_accounts');
            $t->foreignId('receiver_id')->constrained('users');
            $t->bigInteger('amount_minor');
            $t->timestamp('received_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->string('receipt_note', 500)->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('accounting_cash_custody_transfers');
        Schema::dropIfExists('accounting_cash_custodians');
    }
};
