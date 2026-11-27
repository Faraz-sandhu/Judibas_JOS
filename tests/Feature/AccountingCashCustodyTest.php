<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\CashCustody;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingCashCustodyTest extends TestCase
{
    use DatabaseTransactions;
    private int $company;
    private array $accounts;
    private User $ahmed;
    private User $receiver;
    private object $custodian;

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local'); Event::fake();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Custody fixture']);
        config(['product-catalogue.groups' => [['id' => 'custody-test', 'slug' => 'accounts', 'name' => 'Accounting', 'planned' => false, 'companies' => ['Custody fixture']]]]);
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->subYear()->toDateString(), 'approval_required' => false])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        $this->ahmed = $this->member('Ahmed Custody'); $this->receiver = $this->member('Head Office Receiver');
        $this->postJson('/accounting/api/cash-custody/custodians', ['company_id' => $this->company, 'user_id' => $this->ahmed->id, 'parent_id' => $this->accounts['01-01'], 'name' => 'Cash held by Ahmed'])->assertOk();
        $this->custodian = DB::table('accounting_cash_custodians')->where('company_id', $this->company)->first();
        $j = $this->journal('general', $this->accounts['01-01-01'], $this->accounts['03-01'], '50000.00');
        $this->postJournal($j)->assertOk();
    }
    private function member(string $name): User
    {
        $u = User::factory()->create(['name' => $name, 'is_active' => true, 'judibas_administrator' => false]);
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'finance_officer']);
        return $u;
    }
    private function body(string $direction = 'issue', string $amount = '50000.00'): array
    {
        return ['company_id' => $this->company, 'custodian_id' => $this->custodian->id, 'direction' => $direction, 'head_account_id' => $this->accounts['01-01-01'], 'receiver_id' => $direction === 'issue' ? $this->ahmed->id : $this->receiver->id, 'amount' => $amount, 'posting_date' => now()->toDateString(), 'request_key' => (string) Str::uuid(), 'remarks' => 'Cash counted in full'];
    }
    private function journal(string $kind, int $debit, int $credit, string $amount): array
    {
        return $this->postJson('/accounting/api/journals', ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => $kind, 'voucher_person' => 'Client cash collection', 'lines' => [['account_id' => $debit, 'debit' => $amount, 'credit' => '0.00'], ['account_id' => $credit, 'debit' => '0.00', 'credit' => $amount]]])->assertOk()->json('journal');
    }
    private function proof(array $j): void
    {
        $this->post('/accounting/api/journals/'.$j['id'].'/payment-proofs', ['company_id' => $this->company, 'version' => $j['version'], 'payment_proof' => UploadedFile::fake()->create('cash-received.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    }
    private function postJournal(array $j)
    {
        return $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']]);
    }
    private function receive(array $j)
    {
        return $this->postJson('/accounting/api/cash-custody/'.$j['id'].'/receive', ['company_id' => $this->company, 'version' => $j['version'], 'note' => 'Cash counted and received.']);
    }
    public function test_fifty_thousand_issue_and_two_hundred_thousand_collection_return_without_duplicate_income(): void
    {
        $body = $this->body(); $issue = $this->postJson('/accounting/api/cash-custody', $body)->assertOk()->json('journal');
        $this->assertStringStartsWith('CCV-', $issue['number']);
        $this->postJson('/accounting/api/cash-custody', $body)->assertOk()->assertJsonPath('journal.id', $issue['id']);
        $this->proof($issue); $this->postJournal($issue)->assertUnprocessable();
        $this->receive($issue)->assertOk(); $this->receive($issue)->assertOk(); $this->postJournal($issue)->assertOk();
        $receipt = $this->journal('cash_receipt', $this->custodian->account_id, $this->accounts['04-01'], '200000.00');
        $this->proof($receipt); $this->postJournal($receipt)->assertOk();
        $this->assertSame(25000000, CashCustody::balance($this->custodian->account_id));
        $return = $this->postJson('/accounting/api/cash-custody', $this->body('return', '250000.00'))->assertOk()->json('journal');
        $this->receive($return)->assertOk(); $this->proof($return); $this->postJournal($return)->assertOk(); $this->postJournal($return)->assertOk();
        $this->assertSame(0, CashCustody::balance($this->custodian->account_id));
        $this->assertSame(25000000, CashCustody::balance($this->accounts['01-01-01']));
        $this->assertSame(-20000000, CashCustody::balance($this->accounts['04-01']));
        $this->assertSame(2, DB::table('accounting_cash_custody_transfers')->where('company_id', $this->company)->count());
        $this->get('/accounting/vouchers/'.$return['id'].'/print?company_id='.$this->company)->assertOk()->assertSee('Cash Custody Voucher');
    }
    public function test_only_receiver_can_confirm_and_only_super_admin_can_post(): void
    {
        $j = $this->postJson('/accounting/api/cash-custody', $this->body())->assertOk()->json('journal'); $this->proof($j);
        $this->actingAs($this->receiver)->withSession(['judibas_admin' => false]); $this->receive($j)->assertForbidden();
        $this->actingAs($this->ahmed)->withSession(['judibas_admin' => false]); $this->receive($j)->assertOk(); $this->postJournal($j)->assertForbidden();
    }
    public function test_bank_wrong_company_and_overdrawn_cash_are_rejected(): void
    {
        $body = $this->body(); $body['head_account_id'] = $this->accounts['01-01-02']; $this->postJson('/accounting/api/cash-custody', $body)->assertUnprocessable();
        $this->postJson('/accounting/api/cash-custody', $this->body('issue', '50000.01'))->assertUnprocessable();
        $body = $this->body(); $body['company_id'] = DB::table('communication_companies')->insertGetId(['name' => 'Other custody company']);
        $this->postJson('/accounting/api/cash-custody', $body)->assertUnprocessable();
        $this->assertSame(0, DB::table('accounting_cash_custody_transfers')->where('company_id', $this->company)->count());
    }
    public function test_received_voucher_cannot_be_edited_and_insufficient_cash_is_rechecked_at_posting(): void
    {
        $j = $this->postJson('/accounting/api/cash-custody', $this->body())->assertOk()->json('journal'); $this->receive($j)->assertOk(); $this->proof($j);
        $this->postJson('/accounting/api/journals', $j)->assertUnprocessable();
        $spend = $this->journal('general', $this->accounts['01-01-02'], $this->accounts['01-01-01'], '100.00'); $this->postJournal($spend)->assertOk();
        $this->postJournal($j)->assertUnprocessable();
        $this->assertSame(0, CashCustody::balance($this->custodian->account_id));
    }
    public function test_cash_receipt_and_proof_are_independent_and_duplicate_keys_cannot_change_transfer(): void
    {
        $body = $this->body(); $j = $this->postJson('/accounting/api/cash-custody', $body)->assertOk()->json('journal');
        $this->receive($j)->assertOk(); $this->postJournal($j)->assertUnprocessable();
        $body['amount'] = '100.00'; $this->postJson('/accounting/api/cash-custody', $body)->assertConflict();
        $this->assertSame(0, CashCustody::balance($this->custodian->account_id));
        $this->getJson('/accounting/api/cash-custody?company_id='.$this->company)->assertOk()->assertJsonPath('custodians.0.balance', '0.00');
    }

    public function test_existing_invoice_collection_flows_into_custody_without_a_second_receipt(): void
    {
        $this->postJson('/accounting/api/parties', ['company_id' => $this->company, 'kind' => 'customer', 'name' => 'Client cash payer', 'active' => true])->assertOk();
        $party = DB::table('accounting_parties')->where('company_id', $this->company)->first();
        $invoice = $this->postJson('/accounting/api/invoices', ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'kind' => 'sale', 'party_id' => $party->id, 'control_account_id' => $party->account_id, 'posting_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(), 'tax_percent' => '0.00', 'lines' => [['description' => 'Development services', 'account_id' => $this->accounts['04-01'], 'quantity' => '1.00', 'rate' => '200000.00']]])->assertOk()->json('invoice');
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/post', ['company_id' => $this->company, 'version' => $invoice['version']])->assertOk();
        $payment = ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'bank_account_id' => $this->custodian->account_id, 'reference' => 'Ahmed received client cash', 'amount' => '200000.00'];
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $payment)->assertOk()->assertJsonPath('invoice.outstanding', '0.00');
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $payment)->assertOk();
        $this->assertSame(20000000, CashCustody::balance($this->custodian->account_id));
        $this->assertSame(1, DB::table('accounting_payments')->where('invoice_id', $invoice['id'])->count());
        $this->getJson('/accounting/api/cash-custody?company_id='.$this->company)->assertOk()->assertJsonPath('activity.total', 1)->assertJsonPath('activity.data.0.cash_in', '200000.00');
    }
}
