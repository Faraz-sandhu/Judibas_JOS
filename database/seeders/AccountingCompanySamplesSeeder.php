<?php

namespace Database\Seeders;

use App\Modules\Accounting\Http\Controllers\BooksController;
use App\Modules\Accounting\Http\Controllers\ChartOfAccountsController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Http\Controllers\TradeController;
use App\Modules\Accounting\Services\ApprovalPolicy;
use App\Modules\Accounting\Services\Ledger;
use App\Services\CatalogueCompanyScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Small, explicitly fictional samples in existing Accounting companies only. */
class AccountingCompanySamplesSeeder extends AccountingDemoSeeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Sample data is local/testing only.');
        DB::transaction(function () {
            $companies = DB::table('communication_companies')->whereIn('id', CatalogueCompanyScope::ids('accounts', $this->request([])))->orderBy('id')->get();
            $chart = app(ChartOfAccountsController::class);
            $trade = app(TradeController::class);
            $journals = app(JournalController::class);
            $created = $posted = $skipped = 0;
            foreach ($companies as $index => $company) {
                $id = (int) $company->id;
                Ledger::lock($id);
                $book = DB::table('accounting_books')->where('company_id', $id)->first();
                if ($book && (($book->locked_through && $book->locked_through >= now()->toDateString()) || $book->start_date > now()->toDateString())) {
                    $this->command?->warn($company->name.': skipped; no open posting date.');
                    $skipped++;

                    continue;
                }
                if (! $book) {
                    app(BooksController::class)->save($this->request(['company_id' => $id, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->startOfMonth()->toDateString()]));
                    $book = DB::table('accounting_books')->where('company_id', $id)->first();
                }
                if (! DB::table('accounting_accounts')->where('company_id', $id)->exists()) {
                    $chart->initialize($this->request(['company_id' => $id]));
                }
                $accountIds = [];
                foreach (['bank' => ['Asset', 'Bank'], 'capital' => ['Equity', 'Capital'], 'income' => ['Income', 'Sales'], 'expense' => ['Expense', 'Operating Expense'], 'receivable' => ['Asset', 'Receivable'], 'payable' => ['Liability', 'Payable']] as $key => [$root, $type]) {
                    $accountIds[$key] = DB::table('accounting_accounts')->where('company_id', $id)->where('active', true)->where('is_group', false)->whereNull('currency')->where('root_type', $root)->where('account_type', $type)->orderBy('id')->value('id');
                    abort_unless($accountIds[$key], 422, $company->name.' needs an active base-currency '.$type.' account before samples can be added.');
                }
                $date = now()->toDateString();
                $prefix = 'TEST-SAMPLE-V1-'.$id;
                $reference = $prefix.'-CAPITAL';
                if (! DB::table('accounting_journals')->where('company_id', $id)->where('reference', $reference)->exists()) {
                    $journal = $journals->save($this->request(['company_id' => $id, 'request_key' => (string) Str::uuid(), 'posting_date' => $date, 'kind' => 'general', 'reference' => $reference, 'remarks' => 'TEST ONLY — fictional owner funding for Accounting testing.', 'lines' => [
                        ['account_id' => $accountIds['bank'], 'debit' => '50000.00', 'credit' => '0.00'],
                        ['account_id' => $accountIds['capital'], 'debit' => '0.00', 'credit' => '50000.00'],
                    ]]))['journal'];
                    $created++;
                    if (! ApprovalPolicy::required($id, 'general')) {
                        $this->attachDemoPaymentProof($this->request(['company_id'=>$id]),$journal);
                $journals->post($this->request(['company_id' => $id, 'version' => $journal['version']]), $journal['id']);
                        $posted++;
                    }
                }
                foreach (['sale', 'purchase'] as $kind) {
                    $reference = $prefix.'-'.strtoupper($kind);
                    if (DB::table('accounting_invoices')->where('company_id', $id)->where('reference', $reference)->exists()) {
                        continue;
                    }
                    $partyKind = $kind === 'sale' ? 'customer' : 'supplier';
                    $name = 'TEST '.ucfirst($partyKind).' — '.$company->name;
                    $party = DB::table('accounting_parties')->where('company_id', $id)->where('kind', $partyKind)->where('name', $name)->first();
                    if (! $party) {
                        $trade->saveParty($this->request(['company_id' => $id, 'kind' => $partyKind, 'name' => $name, 'active' => true, 'address' => 'Fictional test contact; not a real customer or supplier.']));
                        $party = DB::table('accounting_parties')->where('company_id', $id)->where('kind', $partyKind)->where('name', $name)->first();
                    }
                    $currency = $book->currency;
                    $rate = '1.000000';
                    $amount = $kind === 'sale' ? Ledger::money(1250000 + $index * 50000) : Ledger::money(250000 + $index * 10000);
                    if ($kind === 'sale' && $book->currency === 'PKR' && in_array(strtolower($company->name), ['skyinfinit', 'cruxlo'])) {
                        $currency = strtolower($company->name) === 'skyinfinit' ? 'USD' : 'AED';
                        $rate = $currency === 'USD' ? '282.000000' : '76.800000';
                        $amount = $currency === 'USD' ? '100.00' : '200.00';
                    }
                    $service = in_array($company->name, ['Bedizen', 'Judibas Store', 'China bedding store']) ? 'Sample bedding sales' : (in_array($company->name, ['Judibas Canteen', 'True Water', 'Gaming Lounge']) ? 'Sample customer services' : 'Sample monthly service fee');
                    $invoice = $trade->saveInvoice($this->request(['company_id' => $id, 'request_key' => (string) Str::uuid(), 'kind' => $kind, 'party_id' => $party->id, 'posting_date' => $date, 'due_date' => now()->addDays(14)->toDateString(), 'control_account_id' => $accountIds[$kind === 'sale' ? 'receivable' : 'payable'], 'currency' => $currency, 'exchange_rate' => $rate, 'tax_percent' => '0.00', 'reference' => $reference, 'notes' => 'TEST ONLY — fictional sample. No real payment, statutory tax rate or inventory movement is represented. Exchange rates are examples, not live quotes.', 'lines' => [
                        ['description' => 'TEST ONLY — '.($kind === 'sale' ? $service : 'Office stationery and supplies'), 'quantity' => '1.00', 'rate' => $amount, 'account_id' => $accountIds[$kind === 'sale' ? 'income' : 'expense']],
                    ]]))['invoice'];
                    $created++;
                    if (! ApprovalPolicy::required($id, $kind)) {
                        $trade->postInvoice($this->request(['company_id' => $id, 'version' => $invoice['version']]), $invoice['id']);
                        $posted++;
                    }
                }
                $this->command?->info($company->name.': 3 sample documents available (existing samples retained).');
            }
            $this->command?->info('Created '.$created.' documents; '.$posted.' posted under existing policy; '.($created - $posted).' left as drafts for approval; '.$skipped.' companies skipped.');
        });
    }
}
