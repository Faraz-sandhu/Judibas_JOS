<?php

namespace Database\Seeders;

use App\Modules\Accounting\Http\Controllers\ChartOfAccountsController;
use App\Modules\Accounting\Http\Controllers\FinancialReportsController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingRealisticDemoSeeder extends AccountingDemoSeeder
{
    protected string $companyName = 'Judibas Realistic Accounts Demo (TEST ONLY)';

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        DB::transaction(function () {
            if (DB::table('communication_companies')->where('name', $this->companyName)->exists()) {
                $this->command?->info('Realistic demo already exists; no records changed.');
                return;
            }
            parent::run();
            $company = (int) DB::table('communication_companies')->where('name', $this->companyName)->value('id');
            $chart = app(ChartOfAccountsController::class);
            $ids = DB::table('accounting_accounts')->where('company_id', $company)->pluck('id', 'code')->all();
            // Groups and ledgers are created through the same validation and automatic numbering as the UI.
            $specs = [
                ['01-01', 'Prepaid Rent', 'General'],
                ['01-01', 'Employee Advances', 'General'],
                ['01-01', 'Security Deposits', 'General'],
                ['01', 'Non-current Assets', null],
                ['01-02', 'Office Equipment', 'Fixed Asset'],
                ['01-02', 'Computers and Laptops', 'Fixed Asset'],
                ['01-02', 'Furniture and Fixtures', 'Fixed Asset'],
                ['01-02', 'Accumulated Depreciation', 'General'],
                ['02-01', 'Salaries Payable', 'General'],
                ['02-01', 'Accrued Utilities', 'General'],
                ['02-01', 'Customer Advances', 'General'],
                ['02', 'Long-term Liabilities', null],
                ['02-02', 'Business Loan', 'General'],
                ['03', 'Owner Drawings', 'General'],
                ['03', 'Retained Earnings', 'Retained Earnings'],
                ['04', 'Support and Maintenance Revenue', 'Sales'],
                ['04', 'Other Income', 'Other Income'],
                ['05-01', 'Salaries and Wages', 'Operating Expense'],
                ['05-01', 'Rent Expense', 'Operating Expense'],
                ['05-01', 'Electricity and Utilities', 'Operating Expense'],
                ['05-01', 'Internet and Telephone', 'Operating Expense'],
                ['05-01', 'Software Subscriptions', 'Operating Expense'],
                ['05-01', 'Repairs and Maintenance', 'Operating Expense'],
                ['05-01', 'Travel and Conveyance', 'Operating Expense'],
                ['05-01', 'Bank Charges', 'Operating Expense'],
                ['05-01', 'Depreciation Expense', 'Operating Expense'],
                ['05', 'Direct Costs', null],
                ['05-02', 'Contractor and Project Costs', 'Cost of Sales'],
            ];
            foreach ($specs as [$parentCode, $name, $type]) {
                $parent = DB::table('accounting_accounts')->find($ids[$parentCode]);
                $account = $chart->save($this->request([
                    'company_id' => $company, 'parent_id' => $parent->id, 'name' => $name,
                    'root_type' => $parent->root_type, 'account_type' => $type ?? 'General',
                    'is_group' => $type === null, 'active' => true,
                    'description' => 'Fictional service-business demo account for testing.',
                ]))['account'];
                $ids[$account->code] = $account->id;
            }
            $byName = DB::table('accounting_accounts')->where('company_id', $company)->pluck('id', 'name');
            $samples = [
                ['Computers purchased by bank transfer', 'Computers and Laptops', 'Bank', '85000.00'],
                ['Monthly office rent paid', 'Rent Expense', 'Bank', '35000.00'],
                ['Monthly salaries accrued', 'Salaries and Wages', 'Salaries Payable', '95000.00'],
                ['Partial salary payment', 'Salaries Payable', 'Bank', '65000.00'],
                ['Internet bill paid from cash', 'Internet and Telephone', 'Cash', '4500.00'],
                ['Software subscription paid', 'Software Subscriptions', 'Bank', '7500.00'],
                ['Bank service charge', 'Bank Charges', 'Bank', '450.00'],
                ['Monthly computer depreciation', 'Depreciation Expense', 'Accumulated Depreciation', '3500.00'],
                ['Employee advance paid', 'Employee Advances', 'Cash', '5000.00'],
                ['Owner drawings from bank', 'Owner Drawings', 'Bank', '12000.00'],
                ['Loan received in bank', 'Bank', 'Business Loan', '150000.00'],
                ['Maintenance service income received', 'Bank', 'Support and Maintenance Revenue', '22000.00'],
            ];
            $journals = app(JournalController::class);
            foreach ($samples as [$description, $debit, $credit, $amount]) {
                $journal = $journals->save($this->request([
                    'company_id' => $company, 'request_key' => (string) Str::uuid(),
                    'posting_date' => now()->toDateString(), 'kind' => 'general',
                    'remarks' => $description.' (fictional demo)',
                    'lines' => [
                        ['account_id' => $byName[$debit], 'debit' => $amount, 'credit' => '0.00'],
                        ['account_id' => $byName[$credit], 'debit' => '0.00', 'credit' => $amount],
                    ],
                ]))['journal'];
                $journals->post($this->request(['company_id' => $company, 'version' => $journal['version']]), $journal['id']);
            }
            $reports = app(FinancialReportsController::class);
            foreach (['trial', 'balance'] as $report) {
                $result = $reports->report($this->request(['company_id' => $company, 'report' => $report, 'from' => now()->subDays(60)->toDateString(), 'to' => now()->toDateString()]));
                abort_unless($result['summary'][$report === 'trial' ? 'closing_difference' : 'balance_difference'] === '0.00', 500, 'Demo accounts do not balance.');
            }
            $this->command?->info('Realistic demo verified: '.$byName->count().' accounts, balanced journals, invoices and payments. Open /accounting?company_id='.$company.'#chart');
        });
    }
}
