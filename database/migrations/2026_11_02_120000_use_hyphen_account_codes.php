<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $companies = DB::table('accounting_accounts')->where('code', 'like', '%.%')->distinct()->pluck('company_id')->sort();
            foreach ($companies as $company) {
                DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first();
                $accounts = DB::table('accounting_accounts')->where('company_id', $company)->get();
                foreach ($accounts as $account) {
                    if (! preg_match('/^\d+(?:\.\d+)+$/D', $account->code)) {
                        continue;
                    }
                    $code = str_replace('.', '-', $account->code);
                    if ($accounts->contains('code', $code)) {
                        throw new RuntimeException('Account code conversion conflicts in company '.$company.': '.$code);
                    }
                    DB::table('accounting_accounts')->where('id', $account->id)->update(['code' => $code, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Retain account identifiers; a rollback must not rewrite user-created codes.
    }
};
