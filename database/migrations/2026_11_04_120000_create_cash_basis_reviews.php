<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_cash_basis_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('journal_id')->unique();
            $t->foreign(['company_id', 'journal_id'])->references(['company_id', 'id'])->on('accounting_journals')->restrictOnDelete();
            $t->string('decision', 20);
            $t->bigInteger('outside_minor')->default(0);
            $t->text('note');
            $t->unsignedInteger('version')->default(1);
            $t->string('reviewed_by');
            $t->timestamps();
            $t->unique(['company_id', 'id']);
        });
        Schema::create('accounting_cash_basis_review_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('review_id');
            $t->unsignedBigInteger('account_id');
            $t->bigInteger('amount_minor');
            $t->foreign(['company_id', 'review_id'])->references(['company_id', 'id'])->on('accounting_cash_basis_reviews')->restrictOnDelete();
            $t->foreign(['company_id', 'account_id'])->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
            $t->unique(['review_id', 'account_id']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('accounting_cash_basis_reviews')->exists()) {
            throw new RuntimeException('Cash-basis review decisions must be retained while records exist.');
        }
        Schema::dropIfExists('accounting_cash_basis_review_lines');
        Schema::dropIfExists('accounting_cash_basis_reviews');
    }
};
