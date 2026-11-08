<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeAccountingCodes extends Command
{
    protected $signature = 'accounting:normalize-codes {company : Company ID with an unused chart}';
    protected $description = 'Convert an unused chart to hierarchical 01–05 account codes without changing account IDs';

    public function handle(): int
    {
        $company = (int) $this->argument('company');
        DB::transaction(function () use ($company) {
            abort_unless(DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first(), 404, 'Company not found.');
            abort_if(DB::table('accounting_journals')->where('company_id', $company)->exists(), 422, 'This chart already has journal documents. Its codes have been preserved.');
            $accounts = DB::table('accounting_accounts')->where('company_id', $company)->orderBy('code')->get();
            abort_unless($accounts->count(), 422, 'No chart exists for this company.');
            $codes = [];
            $walk = function ($account, $code) use (&$walk, &$codes, $accounts) {
                abort_if(isset($codes[$account->id]), 422, 'Invalid chart hierarchy.');
                abort_if(strlen($code) > 255, 422, 'Chart exceeds the supported code depth.');
                $codes[$account->id] = $code;
                foreach ($accounts->where('parent_id', $account->id)->values() as $index => $child) {
                    $walk($child, $code.'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT));
                }
            };
            foreach (['Asset' => '01', 'Liability' => '02', 'Equity' => '03', 'Income' => '04', 'Expense' => '05'] as $root => $code) {
                $account = $accounts->where('is_system', true)->firstWhere('root_type', $root);
                abort_unless($account, 422, 'Missing root group.');
                $walk($account, $code);
            }
            abort_unless(count($codes) === $accounts->count(), 422, 'The chart contains accounts outside the root hierarchy.');
            // Vacate old codes first, avoiding unique-key collisions during renumbering.
            foreach ($codes as $id => $code) {
                DB::table('accounting_accounts')->where('id', $id)->update(['code' => 'TMP-'.$id]);
            }
            foreach ($codes as $id => $code) {
                DB::table('accounting_accounts')->where('id', $id)->update(['code' => $code, 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            }
            DB::table('accounting_audits')->insert([
                'company_id' => $company, 'actor_name' => 'Administrator command', 'action' => 'Unused chart codes normalized',
                'subject' => 'Hierarchical 01–05 account codes',
                'details' => json_encode(['previous_codes' => $accounts->pluck('code', 'id'), 'new_codes' => $codes]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        $this->info('Unused chart converted. Account IDs and balances are unchanged.');
        return self::SUCCESS;
    }
}
