<?php

use App\Modules\Accounting\Services\PartyAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_parties', function (Blueprint $t) {
            $t->foreignId('account_id')->nullable()->unique()->constrained('accounting_accounts')->restrictOnDelete();
        });
        foreach (DB::table('accounting_parties')->distinct()->pluck('company_id') as $company) {
            DB::transaction(fn () => PartyAccounts::backfill($company));
        }
    }

    public function down(): void
    { /* Preserve contact-account links and financial history. */
    }
};
