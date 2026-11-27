<?php

namespace Database\Seeders;

use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Services\{ApprovalPolicy, ChartSummary, Ledger};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingCoaBalanceDemoSeeder extends AccountingDemoSeeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'This sample is local/testing only.');
        DB::transaction(function () {
            $company = DB::table('communication_companies')->where('name', 'Receivables Demo (LOCAL TEST ONLY)')->first();
            abort_unless($company, 422, 'The local Receivables Demo company must exist first.');
            Ledger::lock($company->id);
            $book = Ledger::book($company->id);
            Ledger::checkDate($book, now()->toDateString());
            abort_if(ApprovalPolicy::required($company->id, 'general'), 422, 'Use a local demo company without journal approval; existing settings will not be changed.');
            $reference = 'LOCAL-COA-BALANCE-PREVIEW-V1';
            if (DB::table('accounting_journals')->where('company_id', $company->id)->where('reference', $reference)->exists()) {
                $this->command?->info('COA preview already exists; no duplicate entry added.');
                return;
            }
            $ids = [];
            foreach (['Asset' => 'TEST ONLY - Debit balance preview', 'Equity' => 'TEST ONLY - Credit balance preview'] as $root => $name) {
                $parent = DB::table('accounting_accounts')->where('company_id', $company->id)->where('root_type', $root)->where('is_system', true)->where('is_group', true)->where('active', true)->first();
                abort_unless($parent, 422, 'An active '.$root.' root group is required.');
                $ids[$root] = DB::table('accounting_accounts')->insertGetId(['company_id' => $company->id, 'parent_id' => $parent->id, 'code' => ChartSummary::nextCode($company->id, $parent), 'name' => $name, 'root_type' => $root, 'account_type' => 'General', 'is_group' => false, 'is_system' => false, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $controller = app(JournalController::class);
            $journal = $controller->save($this->request(['company_id' => $company->id, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'reference' => $reference, 'remarks' => 'TEST ONLY - fictional balanced entry to preview debit and credit presentation.', 'lines' => [
                ['account_id' => $ids['Asset'], 'debit' => '2500.00', 'credit' => '0.00'],
                ['account_id' => $ids['Equity'], 'debit' => '0.00', 'credit' => '2500.00'],
            ]]))['journal'];
            $controller->post($this->request(['company_id' => $company->id, 'version' => $journal['version']]), $journal['id']);
            $this->command?->info($company->name.': '.$journal['number'].' added. Search "balance preview" in COA: debit 2,500.00; credit (2,500.00).');
        });
    }
}
