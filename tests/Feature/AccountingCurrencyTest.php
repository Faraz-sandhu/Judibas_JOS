<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingCurrencyTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;

    private array $accounts;

    private int $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'FX isolated test']);
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'approval_required' => false, 'start_date' => now()->subYear()->toDateString()])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        $this->customer = DB::table('accounting_parties')->insertGetId(['company_id' => $this->company, 'kind' => 'customer', 'name' => 'FX Customer']);
    }

    private function invoice(array $extra = []): array
    {
        $body = array_replace(['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'kind' => 'sale', 'party_id' => $this->customer, 'posting_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'control_account_id' => $this->accounts['01-01-03'], 'tax_percent' => '0', 'currency' => 'USD', 'exchange_rate' => '280', 'lines' => [['description' => 'Services', 'quantity' => '1', 'rate' => '1000', 'account_id' => $this->accounts['04-01']]]], $extra);
        $i = $this->postJson('/accounting/api/invoices', $body)->assertOk()->json('invoice');

        return $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => $i['version']])->assertOk()->json('invoice');
    }

    private function payment(array $i, string $amount, string $rate, array $extra = [])
    {
        return $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', array_replace(['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'amount' => $amount, 'currency' => 'USD', 'exchange_rate' => $rate, 'bank_account_id' => $this->accounts['01-01-02'], 'reference' => 'FX receipt', 'exchange_account_id' => $this->accounts['04-01']], $extra));
    }

    public function test_invoice_partial_settlement_gain_final_settlement_and_reversal_are_exact(): void
    {
        $i = $this->invoice();
        $this->assertSame('280000.00', $i['total']);
        $this->assertSame('1000.00', $i['foreign_total']);
        $i = $this->payment($i, '400', '282')->assertOk()->assertJsonPath('invoice.outstanding', '168000.00')->assertJsonPath('invoice.foreign_outstanding', '600.00')->json('invoice');
        $p = $i['payments'][0];
        $this->assertSame(11200000, $p['amount_minor']);
        $this->assertSame(11280000, $p['bank_amount_minor']);
        $this->assertSame(80000, $p['exchange_difference_minor']);
        $i = $this->payment($i, '600', '281')->assertOk()->assertJsonPath('invoice.outstanding', '0.00')->assertJsonPath('invoice.foreign_outstanding', '0.00')->json('invoice');
        $trial = $this->getJson('/accounting/api/reports?'.http_build_query(['company_id' => $this->company, 'report' => 'trial', 'from' => now()->toDateString(), 'to' => now()->toDateString()]))->assertOk();
        $trial->assertJsonPath('summary.closing_difference', '0.00');
        $this->getJson('/accounting/api/reports?'.http_build_query(['company_id' => $this->company, 'report' => 'profit', 'basis' => 'cash', 'from' => now()->toDateString(), 'to' => now()->toDateString()]))->assertOk()->assertJsonPath('summary.income', '281400.00');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', ['company_id' => $this->company, 'payment_id' => $p['id'], 'version' => $p['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Reverse FX receipt'])->assertOk()->assertJsonPath('invoice.foreign_outstanding', '400.00')->assertJsonPath('invoice.outstanding', '112000.00');
    }

    public function test_rate_validation_wrong_gain_direction_duplicate_request_and_cross_company_are_rejected(): void
    {
        $i = $this->invoice();
        $this->payment($i, '1001', '282')->assertStatus(422);
        $this->payment($i, '100', '0')->assertStatus(422);
        $this->payment($i, '100', '279')->assertStatus(422);
        $body = ['request_key' => (string) Str::uuid()];
        $this->payment($i, '100', '282', $body)->assertOk();
        $this->payment($i, '100', '282', $body)->assertOk();
        $this->payment($i, '100', '283', $body)->assertStatus(409);
        $this->assertSame(1, DB::table('accounting_payments')->where('company_id', $this->company)->count());
    }

    public function test_foreign_journal_bank_currency_balances_and_saved_originals(): void
    {
        DB::table('accounting_accounts')->where('id', $this->accounts['01-01-02'])->update(['currency' => 'USD']);
        $body = ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'currency' => 'USD', 'exchange_rate' => '282', 'lines' => [['account_id' => $this->accounts['01-01-02'], 'debit' => '1000', 'credit' => '0'], ['account_id' => $this->accounts['03-01'], 'debit' => '0', 'credit' => '1000']]];
        $j = $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.lines.0.debit', '1000.00')->assertJsonPath('journal.lines.0.base_debit', '282000.00')->json('journal');
        $this->postJson('/accounting/api/journals', $body)->assertOk();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk();
        $a = collect($this->getJson('/accounting/api/data?company_id='.$this->company)->assertOk()->json('accounts'))->firstWhere('id', $this->accounts['01-01-02']);
        $this->assertSame('1000.00', $a['foreign_balance']);
        $this->assertSame('282000.00', $a['balance']);
        $this->postJson('/accounting/api/currencies', ['company_id' => $this->company, 'currency' => 'USD', 'exchange_rate' => '300', 'rate_date' => now()->toDateString()])->assertOk();
        $this->assertSame('282.000000', DB::table('accounting_journals')->find($j['id'])->exchange_rate);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/reverse', ['company_id' => $this->company, 'version' => 2, 'posting_date' => now()->toDateString(), 'reason' => 'Reverse foreign journal'])->assertOk();
    }

    public function test_foreign_bank_supplier_payment_exhausts_both_balances_using_carrying_value(): void
    {
        DB::table('accounting_accounts')->where('id', $this->accounts['01-01-02'])->update(['currency' => 'USD']);
        $j = $this->postJson('/accounting/api/journals', ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'currency' => 'USD', 'exchange_rate' => '280', 'lines' => [['account_id' => $this->accounts['01-01-02'], 'debit' => '1000', 'credit' => '0'], ['account_id' => $this->accounts['03-01'], 'debit' => '0', 'credit' => '1000']]])->assertOk()->json('journal');
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertOk();
        $supplier = DB::table('accounting_parties')->insertGetId(['company_id' => $this->company, 'kind' => 'supplier', 'name' => 'FX Supplier']);
        $i = $this->invoice(['kind' => 'purchase', 'party_id' => $supplier, 'control_account_id' => $this->accounts['02-01-01'], 'exchange_rate' => '282', 'lines' => [['description' => 'Service cost', 'quantity' => '1', 'rate' => '1000', 'account_id' => $this->accounts['05-01-01']]]]);
        $i = $this->payment($i, '1000', '283')->assertOk()->assertJsonPath('invoice.outstanding', '0.00')->json('invoice');
        $this->assertSame(28000000, $i['payments'][0]['bank_amount_minor']);
        $a = collect($this->getJson('/accounting/api/data?company_id='.$this->company)->assertOk()->json('accounts'))->firstWhere('id', $this->accounts['01-01-02']);
        $this->assertSame('0.00', $a['foreign_balance']);
        $this->assertSame('0.00', $a['balance']);
    }

    public function test_foreign_loss_and_pending_payment_reservations_use_original_units(): void
    {
        $i = $this->invoice();
        $this->payment($i, '100', '279', ['exchange_account_id' => $this->accounts['05-01-01']])->assertOk()->assertJsonPath('invoice.foreign_outstanding', '900.00');
        DB::table('accounting_global_policy')->where('id', 1)->update(['approval_required' => true, 'approval_types' => json_encode(['customer_payment'])]);
        $this->payment($i,'600','280',['exchange_account_id' => null])->assertOk();
        $this->payment($i,'400','280',['exchange_account_id' => null])->assertStatus(422);
        $this->assertSame(1,DB::table('accounting_payment_requests')->where('company_id',$this->company)->count());
    }
}
