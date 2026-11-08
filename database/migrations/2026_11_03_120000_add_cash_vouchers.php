<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_journals', fn (Blueprint $t) => $t->string('voucher_person', 150)->nullable());
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_journals DROP CONSTRAINT accounting_journal_kind');
            DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_kind CHECK (kind IN ('general','opening','reversal','cash_receipt','cash_payment'))");
        }
    }

    public function down(): void
    {
        if (DB::table('accounting_journals')->whereIn('kind', ['cash_receipt', 'cash_payment'])->exists()) {
            throw new RuntimeException('Retain the cash voucher schema while voucher records exist.');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounting_journals DROP CONSTRAINT accounting_journal_kind');
            DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_kind CHECK (kind IN ('general','opening','reversal'))");
        }
        Schema::table('accounting_journals', fn (Blueprint $t) => $t->dropColumn('voucher_person'));
    }
};
