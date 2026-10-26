<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->string('kind', 20);
            $t->string('name', 150);
            $t->string('email')->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('tax_number', 100)->nullable();
            $t->text('address')->nullable();
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'id']);
        });
        Schema::table('accounting_journal_lines', function (Blueprint $t) {
            $t->unsignedBigInteger('party_id')->nullable();
            $t->foreign(['company_id', 'party_id'])->references(['company_id', 'id'])->on('accounting_parties')->restrictOnDelete();
        });
        Schema::create('accounting_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->unsignedBigInteger('party_id');
            $t->foreign(['company_id', 'party_id'])->references(['company_id', 'id'])->on('accounting_parties')->restrictOnDelete();
            $t->string('party_name', 150);
            $t->string('kind', 20);
            $t->uuid('request_key');
            $t->string('number', 40);
            $t->date('posting_date');
            $t->date('due_date');
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->string('reference', 150)->nullable();
            $t->text('notes')->nullable();
            $t->json('lines');
            $t->bigInteger('net_minor');
            $t->bigInteger('tax_minor')->default(0);
            $t->bigInteger('total_minor');
            $t->unsignedInteger('tax_basis_points')->default(0);
            $t->string('tax_label', 150)->nullable();
            $t->unsignedBigInteger('tax_account_id')->nullable();
            $t->unsignedBigInteger('control_account_id');
            $t->foreign(['company_id', 'tax_account_id'])->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            $t->foreign(['company_id', 'control_account_id'])->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            $t->unsignedBigInteger('journal_id')->nullable()->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('accounting_journals')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'id']);
            $t->unique(['company_id', 'request_key']);
            $t->unique(['company_id', 'number']);
            $t->index(['company_id', 'posting_date']);
        });
        Schema::create('accounting_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('invoice_id');
            $t->foreign(['company_id', 'invoice_id'])->references(['company_id', 'id'])->on('accounting_invoices')->restrictOnDelete();
            $t->uuid('request_key');
            $t->string('number', 40);
            $t->date('posting_date');
            $t->bigInteger('amount_minor');
            $t->unsignedBigInteger('bank_account_id');
            $t->foreign(['company_id', 'bank_account_id'])->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            $t->string('reference', 150);
            $t->string('status', 20)->default('posted');
            $t->unsignedBigInteger('journal_id')->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('accounting_journals')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'request_key']);
            $t->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_payments');
        Schema::dropIfExists('accounting_invoices');
        Schema::table('accounting_journal_lines', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'party_id']);
            $t->dropColumn('party_id');
        });
        Schema::dropIfExists('accounting_parties');
    }
};
