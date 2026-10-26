<?php

namespace Database\Seeders;

use App\Modules\Accounting\Http\Controllers\{BooksController, ChartOfAccountsController, JournalController, TradeController, FinancialReportsController};
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingDemoSeeder extends Seeder
{
    private function request(array $input): Request
    {
        $r = Request::create('/accounting-demo', 'POST', $input);
        $session = app('session')->driver();
        $session->put('judibas_admin', true);
        $r->setLaravelSession($session);

        return $r;
    }

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Accounting sample data is restricted to local/testing environments.');
        $name = 'Judibas Accounting Demo (TEST ONLY)';
        if (DB::table('communication_companies')->where('name', $name)->exists()) {
            $this->command?->info('Accounting demo already exists; no records were changed.');

            return;
        }
        DB::transaction(function () use ($name) {
            $company = DB::table('communication_companies')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $base = ['company_id' => $company];
            $start = now()->subDays(60)->toDateString();
            app(ChartOfAccountsController::class)->initialize($this->request($base));
            app(BooksController::class)->save($this->request($base + ['version' => 0, 'currency' => 'PKR', 'start_date' => $start]));
            $accounts = DB::table('accounting_accounts')->where('company_id', $company)->pluck('id', 'code')->all();
            $trade = app(TradeController::class);
            foreach (['customer' => ['Cedar Retail (Demo)', 'Atlas Logistics (Demo)', 'Northstar Studio (Demo)'], 'supplier' => ['Office Supplies (Demo)', 'Cloud Services (Demo)', 'City Utilities (Demo)']] as $kind => $names) {
                foreach ($names as $n => $party) {
                    $trade->saveParty($this->request($base + ['kind' => $kind, 'name' => $party, 'email' => $kind.($n + 1).'@example.test', 'phone' => '000-0000000', 'address' => 'Fictional sample address, Pakistan', 'tax_number' => 'DEMO-NOT-A-REAL-NTN', 'active' => true]));
                }
            }
            $customers = DB::table('accounting_parties')->where('company_id', $company)->where('kind', 'customer')->orderBy('id')->pluck('id')->all();
            $suppliers = DB::table('accounting_parties')->where('company_id', $company)->where('kind', 'supplier')->orderBy('id')->pluck('id')->all();
            $journals = app(JournalController::class);
            $makeJournal = function (string $description, string $date, string $kind, array $lines, bool $post = true) use ($journals, $base) {
                $j = $journals->save($this->request($base + ['request_key' => (string) Str::uuid(), 'posting_date' => $date, 'kind' => $kind, 'remarks' => $description.' (fictional demo)', 'lines' => $lines]))['journal'];
                if ($post) $journals->post($this->request($base + ['version' => $j['version']]), $j['id']);

                return $j;
            };
            $makeJournal('Opening capital', $start, 'opening', [['account_id' => $accounts['1120'], 'debit' => '500000.00', 'credit' => '0'], ['account_id' => $accounts['1110'], 'debit' => '50000.00', 'credit' => '0'], ['account_id' => $accounts['3100'], 'debit' => '0', 'credit' => '550000.00']]);
            $makeJournal('Bank to petty cash', now()->subDays(3)->toDateString(), 'general', [['account_id' => $accounts['1110'], 'debit' => '10000.00', 'credit' => '0'], ['account_id' => $accounts['1120'], 'debit' => '0', 'credit' => '10000.00']]);
            $makeJournal('Draft office expense for review', now()->toDateString(), 'general', [['account_id' => $accounts['5110'], 'debit' => '2500.00', 'credit' => '0'], ['account_id' => $accounts['1110'], 'debit' => '0', 'credit' => '2500.00']], false);
            $scenarios = [
                ['sale', $customers[0], 'Website development', '120000.00', 20, '40000.00', false],
                ['sale', $customers[1], 'Monthly support', '30000.00', 8, '30000.00', false],
                ['sale', $customers[2], 'Design services - overdue', '45000.00', 45, null, false],
                ['sale', $customers[0], 'Draft proposal invoice', '18000.00', 0, null, true],
                ['purchase', $suppliers[0], 'Office stationery', '12500.00', 12, '5000.00', false],
                ['purchase', $suppliers[1], 'Hosting services', '8000.00', 5, '8000.00', false],
                ['purchase', $suppliers[2], 'Utility bill - unpaid', '6200.00', 35, null, false],
                ['purchase', $suppliers[0], 'Draft equipment expense', '15000.00', 0, null, true],
            ];
            foreach ($scenarios as [$kind, $party, $description, $amount, $daysAgo, $payment, $draft]) {
                $date = now()->subDays($daysAgo)->toDateString();
                $invoice = $trade->saveInvoice($this->request($base + ['request_key' => (string) Str::uuid(), 'kind' => $kind, 'party_id' => $party, 'control_account_id' => $accounts[$kind === 'sale' ? '1130' : '2110'], 'posting_date' => $date, 'due_date' => now()->subDays($daysAgo)->addDays(14)->toDateString(), 'tax_percent' => '0.00', 'notes' => 'Fictional testing data. No statutory tax rate assumed.', 'lines' => [['description' => $description, 'quantity' => '1', 'rate' => $amount, 'account_id' => $accounts[$kind === 'sale' ? '4100' : '5110']]]]))['invoice'];
                if (! $draft) {
                    $trade->postInvoice($this->request($base + ['version' => $invoice['version']]), $invoice['id']);
                    if ($payment) $trade->payment($this->request($base + ['request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'amount' => $payment, 'bank_account_id' => $accounts['1120'], 'reference' => 'DEMO transfer - not a real payment']), $invoice['id']);
                }
            }
            $report = app(FinancialReportsController::class)->report($this->request($base + ['report' => 'balance', 'from' => $start, 'to' => now()->toDateString()]));
            abort_unless($report['summary']['balance_difference'] === '0.00', 500, 'Sample balance sheet failed verification.');
            $this->command?->info('Created '.$name.' with PKR books, 6 contacts, 8 invoices, 4 payments and 3 standalone journals. Balance sheet verified.');
        });
    }
}
