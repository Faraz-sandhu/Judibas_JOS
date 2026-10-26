<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingTradeTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;
    private array $accounts;
    private int $customer;
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Trade test company']);
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->subYear()->toDateString()])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        foreach (['customer', 'supplier'] as $kind) {
            $this->postJson('/accounting/api/parties', ['company_id' => $this->company, 'kind' => $kind, 'name' => 'Example '.$kind, 'active' => true])->assertOk();
        }
        $this->customer = DB::table('accounting_parties')->where('company_id', $this->company)->where('kind', 'customer')->value('id');
        $this->supplier = DB::table('accounting_parties')->where('company_id', $this->company)->where('kind', 'supplier')->value('id');
    }

    private function body(string $kind = 'sale', array $replace = []): array
    {
        return array_replace(['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'kind' => $kind, 'party_id' => $kind === 'sale' ? $this->customer : $this->supplier, 'control_account_id' => $this->accounts[$kind === 'sale' ? '1130' : '2110'], 'posting_date' => now()->toDateString(), 'due_date' => now()->addDays(15)->toDateString(), 'tax_percent' => '0.00', 'lines' => [['description' => 'Consulting service', 'account_id' => $this->accounts[$kind === 'sale' ? '4100' : '5110'], 'quantity' => '2.50', 'rate' => '100.01']]], $replace);
    }

    private function create(array $body, bool $post = true): array
    {
        $i = $this->postJson('/accounting/api/invoices', $body)->assertOk()->json('invoice');
        if ($post) $i = $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => $i['version']])->assertOk()->json('invoice');

        return $i;
    }

    private function payment(array $invoice, string $amount = '100.00'): array
    {
        return ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'bank_account_id' => $this->accounts['1120'], 'reference' => 'Bank transfer test', 'amount' => $amount];
    }

    private function report(string $type)
    {
        return $this->getJson('/accounting/api/reports?'.http_build_query(['company_id' => $this->company, 'report' => $type, 'from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()]));
    }

    public function test_sales_invoice_posting_and_partial_payment_update_receivable_correctly(): void
    {
        $body = $this->body();$i = $this->create($body, false);
        $this->assertSame('250.03', $i['total']);
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->postJson('/accounting/api/invoices', $body)->assertOk()->assertJsonPath('invoice.id', $i['id']);
        $i = $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertOk()->json('invoice');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertOk();
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '250.03');
        $p = $this->payment($i);
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $p)->assertOk()->assertJsonPath('invoice.outstanding', '150.03')->assertJsonPath('invoice.paid', '100.00');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $p)->assertOk()->assertJsonCount(1, 'invoice.payments');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', array_replace($p, ['amount' => '101.00']))->assertConflict();
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $this->payment($i, '150.04'))->assertUnprocessable();
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $this->payment($i, '150.03'))->assertOk()->assertJsonPath('invoice.outstanding', '0.00');
        $this->report('trial')->assertOk()->assertJsonPath('summary.closing_difference', '0.00');
        $this->report('balance')->assertOk()->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_purchase_invoice_and_supplier_payment_have_correct_ledger_direction(): void
    {
        $i = $this->create($this->body('purchase'));
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '-250.03');
        $this->report('balance')->assertOk()->assertJsonPath('summary.liabilities', '250.03')->assertJsonPath('summary.balance_difference', '0.00');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $this->payment($i, '250.03'))->assertOk()->assertJsonPath('invoice.outstanding', '0.00');
        $line = DB::table('accounting_journal_lines')->where('account_id', $this->accounts['1120'])->where('company_id', $this->company)->first();
        $this->assertSame(25003, $line->credit_minor);
        $this->assertSame(0, $line->debit_minor);
    }

    public function test_configured_tax_is_rounded_exactly_and_posted_to_tax_account(): void
    {
        $tax = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['2100'], 'code' => '2120', 'name' => 'Output tax', 'root_type' => 'Liability', 'account_type' => 'Tax']);
        $i = $this->create($this->body('sale', ['tax_percent' => '18.00', 'tax_label' => 'Accountant-approved test rate', 'tax_account_id' => $tax]));
        $this->assertSame('45.01', $i['tax']);$this->assertSame('295.04', $i['total']);
        $this->assertDatabaseHas('accounting_journal_lines', ['journal_id' => $i['journal_id'], 'account_id' => $tax, 'credit_minor' => 4501]);
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '295.04')->assertJsonPath('summary.liabilities', '45.01')->assertJsonPath('summary.balance_difference', '0.00');
        $this->postJson('/accounting/api/invoices', $this->body('sale', ['tax_percent' => '101.00']))->assertUnprocessable();
        $this->postJson('/accounting/api/invoices', $this->body('sale', ['tax_percent' => '18.00']))->assertUnprocessable();
    }

    public function test_payment_reversal_restores_outstanding_and_invoice_reversal_offsets_ledger(): void
    {
        $i = $this->create($this->body());
        $i = $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $this->payment($i))->assertOk()->json('invoice');
        $body = ['company_id' => $this->company, 'version' => $i['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Incorrect invoice and transfer'];
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', $body)->assertUnprocessable();
        $p = $i['payments'][0];
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', array_replace($body, ['payment_id' => $p['id'], 'version' => $p['version']]))->assertOk()->assertJsonPath('invoice.outstanding', '250.03');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', $body)->assertOk()->assertJsonPath('invoice.status', 'reversed')->assertJsonPath('invoice.outstanding', '0.00');
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '0.00')->assertJsonPath('summary.liabilities', '0.00')->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_source_journal_cannot_be_reversed_independently_of_invoice(): void
    {
        $i = $this->create($this->body());
        $this->postJson('/accounting/api/journals/'.$i['journal_id'].'/reverse', ['company_id' => $this->company, 'version' => 2, 'posting_date' => now()->toDateString(), 'reason' => 'Bypass source document'])->assertUnprocessable();
    }

    public function test_draft_version_identity_and_posted_invoice_are_protected(): void
    {
        $body = $this->body();$i = $this->create($body, false);
        $edited = array_replace($body, ['id' => $i['id'], 'version' => 1, 'reference' => 'Updated reference']);
        $this->postJson('/accounting/api/invoices', $edited)->assertOk()->assertJsonPath('invoice.version', 2);
        $this->postJson('/accounting/api/invoices', $edited)->assertConflict();
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertConflict();
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/post', ['company_id' => $this->company, 'version' => 2])->assertOk();
        $this->postJson('/accounting/api/invoices', array_replace($edited, ['version' => 3]))->assertConflict();
    }

    public function test_party_kind_cross_company_and_viewer_restrictions(): void
    {
        $this->postJson('/accounting/api/invoices', $this->body('sale', ['party_id' => $this->supplier]))->assertUnprocessable();
        $this->postJson('/accounting/api/invoices', $this->body('sale', ['control_account_id' => $this->accounts['1110']]))->assertUnprocessable();
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer']);
        $this->withSession(['judibas_admin' => false])->actingAs($u);
        $this->getJson('/accounting/api/parties?company_id='.$this->company)->assertOk();
        $this->postJson('/accounting/api/invoices', $this->body())->assertForbidden();
        $this->postJson('/accounting/api/parties', ['company_id' => $this->company, 'kind' => 'customer', 'name' => 'Forbidden', 'active' => true])->assertForbidden();
    }

    public function test_date_stockless_invoice_and_negative_values_are_validated(): void
    {
        $body = $this->body();$body['lines'][0]['quantity'] = '-1';
        $this->postJson('/accounting/api/invoices', $body)->assertUnprocessable();
        $body = $this->body();$body['lines'][0]['rate'] = '1.001';
        $this->postJson('/accounting/api/invoices', $body)->assertUnprocessable();
        $this->postJson('/accounting/api/invoices', $this->body('sale', ['posting_date' => now()->addDay()->toDateString()]))->assertUnprocessable();
        $i = $this->create($this->body(), false);
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $this->payment($i))->assertUnprocessable();
    }

    public function test_aging_preserves_historical_payment_and_reversal_dates(): void
    {
        $i = $this->create($this->body('sale', ['posting_date' => now()->subDays(45)->toDateString(), 'due_date' => now()->subDays(35)->toDateString()]));
        $p = array_replace($this->payment($i), ['posting_date' => now()->subDays(10)->toDateString()]);
        $i = $this->postJson('/accounting/api/invoices/'.$i['id'].'/payments', $p)->assertOk()->json('invoice');
        $report = fn ($date) => $this->getJson('/accounting/api/reports?'.http_build_query(['company_id' => $this->company, 'report' => 'receivables', 'from' => now()->subYear()->toDateString(), 'to' => $date]));
        $report(now()->subDays(15)->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '250.03')->assertJsonPath('summary.days_1_30', '250.03');
        $report(now()->subDay()->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '150.03')->assertJsonPath('summary.days_31_60', '150.03');
        $body = ['company_id' => $this->company, 'posting_date' => now()->toDateString(), 'reason' => 'Reverse historical invoice'];
        $p = $i['payments'][0];
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', $body + ['payment_id' => $p['id'], 'version' => $p['version']])->assertOk();
        $report(now()->subDay()->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '150.03');
        $report(now()->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '250.03');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/reverse', $body + ['version' => $i['version']])->assertOk();
        $report(now()->subDay()->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '150.03');
        $report(now()->toDateString())->assertOk()->assertJsonPath('summary.outstanding', '0.00');
    }

    public function test_invoice_print_uses_historical_contact_details_and_requires_company_access(): void
    {
        DB::table('accounting_parties')->where('id', $this->customer)->update(['address' => 'Original customer address', 'tax_number' => 'Original tax reference']);
        $i = $this->create($this->body());
        DB::table('accounting_parties')->where('id', $this->customer)->update(['address' => 'Changed address']);
        $this->get('/accounting/invoices/'.$i['id'].'/print')->assertOk()->assertSee('Original customer address')->assertDontSee('Changed address');
        $u = User::factory()->create(['is_active' => true]);
        $this->withSession(['judibas_admin' => false])->actingAs($u);
        $this->get('/accounting/invoices/'.$i['id'].'/print')->assertForbidden();
    }

    public function test_database_prevents_modifying_posted_invoice_amounts(): void
    {
        $i = $this->create($this->body());
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::transaction(fn () => DB::table('accounting_invoices')->where('id', $i['id'])->update(['net_minor' => 1, 'total_minor' => 1]));
    }
}
