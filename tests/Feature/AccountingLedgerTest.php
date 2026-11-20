<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\Ledger;
use App\Services\UserProductAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Ledger test company', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        foreach (['1000' => '01', '1100' => '01-01', '1110' => '01-01-01', '1120' => '01-01-02', '1130' => '01-01-03', '2000' => '02', '2100' => '02-01', '2110' => '02-01-01', '3000' => '03', '3100' => '03-01', '4000' => '04', '4100' => '04-01', '5000' => '05', '5100' => '05-01', '5110' => '05-01-01'] as $legacy => $code) { $this->accounts[$legacy] = $this->accounts[$code]; }
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'approval_required' => false, 'start_date' => now()->subYear()->toDateString()])->assertOk();
    }

    public function test_chart_rolls_up_posted_amounts_and_excludes_drafts(): void
    {
        $this->create($this->body('900.00'), false);
        $this->create($this->body('125.75'));
        $rows = collect($this->getJson('/accounting/api/data?company_id='.$this->company)->assertOk()->json('accounts'))->keyBy('id');
        foreach (['1000', '1100', '1110'] as $code) {
            $this->assertSame('125.75', $rows[$this->accounts[$code]]['debit']);
            $this->assertSame('125.75', $rows[$this->accounts[$code]]['balance']);
            $this->assertSame('Dr', $rows[$this->accounts[$code]]['balance_side']);
        }
        $this->assertSame('125.75', $rows[$this->accounts['4000']]['credit']);
        $this->assertSame('Cr', $rows[$this->accounts['4000']]['balance_side']);
        $this->assertSame('0.00', $rows[$this->accounts['3000']]['balance']);
    }

    public function test_journals_can_be_saved_and_posted_without_a_description(): void
    {
        foreach (['', null, '   '] as $description) {
            $body = $this->body('25.00', ['remarks' => $description]);
            $journal = $this->create($body);
            $this->assertSame('', $journal['remarks']);
            $this->assertSame('posted', $journal['status']);
            $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.id', $journal['id']);
        }
        $body = $this->body('25.00');
        unset($body['remarks']);
        $this->assertSame('', $this->create($body)['remarks']);
    }

    public function test_cash_vouchers_post_once_filter_print_and_reverse_in_the_same_ledger(): void
    {
        $receipt = $this->body('500.25', ['kind' => 'cash_receipt', 'voucher_person' => 'Walk-in service customer']);
        $r = $this->create($receipt);
        $this->assertStringStartsWith('CRV-', $r['number']);
        $this->postJson('/accounting/api/journals', $receipt)->assertOk()->assertJsonPath('journal.id', $r['id']);
        $this->get('/accounting/vouchers/'.$r['id'].'/print?company_id='.$this->company)->assertOk()->assertSee('Cash Receipt Voucher')->assertSee('Walk-in service customer');
        $payment = $this->body('100.10', ['kind' => 'cash_payment', 'voucher_person' => 'Office vendor']);
        $payment['lines'] = [['account_id' => $this->accounts['1110'], 'debit' => '0.00', 'credit' => '100.10'], ['account_id' => $this->accounts['5110'], 'debit' => '100.10', 'credit' => '0.00']];
        $p = $this->create($payment);
        $this->assertStringStartsWith('CPV-', $p['number']);
        $this->report('ledger', ['account_id' => $this->accounts['1110']])->assertOk()->assertJsonPath('closing', '400.15');
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&kind=cash_receipt')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $r['id']);
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertOk()->assertJsonPath('total', 0);
        $this->postJson('/accounting/api/journals/'.$p['id'].'/reverse', ['company_id' => $this->company, 'version' => $p['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Incorrect cash payment'])->assertOk();
        $this->report('ledger', ['account_id' => $this->accounts['1110']])->assertOk()->assertJsonPath('closing', '500.25');
        $this->report('trial')->assertOk()->assertJsonPath('summary.closing_difference', '0.00');
    }

    public function test_cash_vouchers_reject_missing_person_wrong_direction_and_non_cash_accounts(): void
    {
        $body = $this->body('50.00', ['kind' => 'cash_receipt']);
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['voucher_person'] = 'Test person';
        $body['kind'] = 'cash_payment';
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['kind'] = 'cash_receipt';
        $body['lines'][0]['account_id'] = $this->accounts['1120'];
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['lines'][0]['account_id'] = $this->accounts['1110'];
        $body['lines'][1]['account_id'] = $this->accounts['1130'];
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['lines'][1]['account_id'] = $this->accounts['4100'];
        $body['lines'][1]['credit'] = '49.00';
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
    }

    public function test_trial_formats_split_opening_sides_and_export_only_selected_amount_columns(): void
    {
        $body = $this->body('100.25', ['kind' => 'opening', 'posting_date' => now()->subYear()->toDateString()]);
        $body['lines'][1]['account_id'] = $this->accounts['3100'];
        $this->create($body);
        $this->create($this->body('50.10'));
        $rows = collect($this->report('trial')->assertOk()->json('rows'))->keyBy('account_id');
        $this->assertSame('100.25', $rows[$this->accounts['1110']]['opening_debit']);
        $this->assertSame('0.00', $rows[$this->accounts['1110']]['opening_credit']);
        $this->assertSame('100.25', $rows[$this->accounts['3100']]['opening_credit']);
        $this->assertSame('150.35', $rows[$this->accounts['1110']]['closing_debit']);
        foreach (['2' => 5, '6' => 9] as $format => $columns) {
            $response = $this->report('trial', ['export' => 'csv', 'trial_columns' => (string) $format])->assertOk();
            $csv = $response->streamedContent();
            $header = str_getcsv(explode("\n", trim($csv))[0]);
            $this->assertCount($columns, $header);
            $this->assertSame('Closing debit', $header[count($header) - 2]);
            if ((string) $format === '6') $this->assertContains('Opening credit', $header);
            else $this->assertNotContains('Opening credit', $header);
        }
    }

    private function cashPayment(string $amount, array $replace = []): array
    {
        return $this->body($amount, array_replace(['kind' => 'cash_payment', 'voucher_person' => 'Cash vendor', 'lines' => [
            ['account_id' => $this->accounts['1110'], 'debit' => '0.00', 'credit' => $amount],
            ['account_id' => $this->accounts['5110'], 'debit' => $amount, 'credit' => '0.00'],
        ]], $replace));
    }

    public function test_cash_payment_cannot_be_created_using_zero_draft_or_later_funds(): void
    {
        $payment = $this->cashPayment('10.00');
        $this->postJson('/accounting/api/journals', $payment)->assertUnprocessable()->assertJsonFragment(['message' => 'Insufficient cash balance on '.now()->toDateString().'. Available: PKR 0.00. Payment: PKR 10.00.']);
        $this->assertDatabaseMissing('accounting_journals', ['request_key' => $payment['request_key']]);
        $this->create($this->body('100.00'), false);
        $this->postJson('/accounting/api/journals', $payment)->assertUnprocessable();
        $this->create($this->body('100.00'));
        $this->postJson('/accounting/api/journals', $this->cashPayment('10.00', ['posting_date' => now()->subDay()->toDateString()]))->assertUnprocessable();
        $this->postJson('/accounting/api/journals', $this->cashPayment('100.01'))->assertUnprocessable();
    }

    public function test_posting_rechecks_cash_and_exact_balance_payment_is_idempotent(): void
    {
        $this->create($this->body('100.00'));
        $pending = $this->create($this->cashPayment('80.00'), false);
        $this->create($this->cashPayment('60.00'));
        $this->postJson('/accounting/api/journals/'.$pending['id'].'/post', ['company_id' => $this->company, 'version' => $pending['version']])->assertUnprocessable()->assertJsonFragment(['message' => 'Insufficient cash balance on '.now()->toDateString().'. Available: PKR 40.00. Payment: PKR 80.00.']);
        $this->assertDatabaseHas('accounting_journals', ['id' => $pending['id'], 'status' => 'draft']);
        $body = $this->cashPayment('40.00');
        $paid = $this->create($body);
        $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.id', $paid['id']);
        $this->postJson('/accounting/api/journals/'.$paid['id'].'/post', ['company_id' => $this->company, 'version' => $paid['version']])->assertOk();
        $this->report('ledger', ['account_id' => $this->accounts['1110']])->assertOk()->assertJsonPath('closing', '0.00');
    }

    public function test_reversed_cash_receipts_do_not_supply_available_funds(): void
    {
        $receipt = $this->create($this->body('100.00', ['kind' => 'cash_receipt', 'voucher_person' => 'Walk-in customer']));
        $this->postJson('/accounting/api/journals/'.$receipt['id'].'/reverse', ['company_id' => $this->company, 'version' => $receipt['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Receipt cancelled'])->assertOk();
        $this->postJson('/accounting/api/journals', $this->cashPayment('1.00'))->assertUnprocessable();
        // Receipts can still be created when the cash balance is zero.
        $this->create($this->body('20.00', ['kind' => 'cash_receipt', 'voucher_person' => 'Another customer']));
    }

    public function test_cash_profit_recognises_direct_cash_and_bank_entries_and_flags_unallocated_settlements(): void
    {
        $this->create($this->body('40.25'));
        $this->create($this->body('10.00', ['kind' => 'cash_receipt', 'voucher_person' => 'Cash customer']));
        $this->create($this->cashPayment('6.00'));
        $liability = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['2000'], 'code' => '02-02', 'name' => 'Salaries payable', 'root_type' => 'Liability', 'account_type' => 'General']);
        $this->create($this->body('100.00', ['lines' => [
            ['account_id' => $this->accounts['5110'], 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $liability, 'debit' => '0.00', 'credit' => '100.00'],
        ]]));
        $settlement = $this->create($this->body('50.00', ['lines' => [
            ['account_id' => $liability, 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['1120'], 'debit' => '0.00', 'credit' => '50.00'],
        ]]));
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.income', '50.25')->assertJsonPath('summary.expense', '6.00')->assertJsonPath('summary.profit', '44.25')->assertJsonPath('cash_basis_complete', false)->assertJsonCount(1, 'cash_basis_review')->assertJsonPath('cash_basis_review.0.id', $settlement['id']);
        $this->report('profit')->assertOk()->assertJsonPath('basis', 'accrual')->assertJsonPath('summary.expense', '106.00');
        $this->assertStringContainsString('Excluded - needs review', $this->report('profit', ['basis' => 'cash', 'export' => 'csv'])->assertOk()->streamedContent());
    }

    public function test_cash_profit_reverses_direct_transactions_and_excludes_capital_and_transfers(): void
    {
        $receipt = $this->create($this->body('40.25'));
        $this->create($this->body('100.00', ['lines' => [
            ['account_id' => $this->accounts['1120'], 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['3100'], 'debit' => '0.00', 'credit' => '100.00'],
        ]]));
        $this->create($this->body('10.00', ['lines' => [
            ['account_id' => $this->accounts['1110'], 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['1120'], 'debit' => '0.00', 'credit' => '10.00'],
        ]]));
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.income', '40.25')->assertJsonPath('cash_basis_complete', true);
        $this->postJson('/accounting/api/journals/'.$receipt['id'].'/reverse', ['company_id' => $this->company, 'version' => $receipt['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Cancel direct receipt'])->assertOk();
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.income', '0.00')->assertJsonPath('summary.profit', '0.00');
        $this->withSession(['judibas_admin' => false]);
        $this->report('profit', ['basis' => 'cash'])->assertForbidden();
    }

    private function unallocatedCash(string $date = ''): array
    {
        $liability = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['2000'], 'code' => '02-02', 'name' => 'Salary accrual', 'root_type' => 'Liability', 'account_type' => 'General']);
        return $this->create($this->body('50.00', ['posting_date' => $date ?: now()->toDateString(), 'remarks' => 'Salary cash settlement', 'lines' => [
            ['account_id' => $liability, 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['1120'], 'debit' => '0.00', 'credit' => '50.00'],
        ]]));
    }

    private function cashReviewBody(array $replace = []): array
    {
        return array_replace(['company_id' => $this->company, 'version' => 0, 'decision' => 'allocate', 'note' => 'This cash payment settles salary expense.', 'outside_amount' => '0.00',
            'allocations' => [['account_id' => $this->accounts['5110'], 'amount' => '50.00', 'refund' => false]]], $replace);
    }

    public function test_cash_basis_review_resolves_updates_and_audits_without_changing_the_ledger(): void
    {
        $journal = $this->unallocatedCash();
        $before = DB::table('accounting_journal_lines')->where('journal_id', $journal['id'])->get()->toJson();
        $url = '/accounting/api/cash-basis-reviews/'.$journal['id'];
        $this->getJson($url.'?company_id='.$this->company)->assertOk()->assertJsonPath('can_resolve', true)->assertJsonPath('net_cash', '-50.00');
        $this->postJson($url, $this->cashReviewBody())->assertOk();
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.expense', '50.00')->assertJsonPath('cash_basis_complete', true)->assertJsonCount(1, 'cash_basis_resolved');
        $this->report('profit')->assertOk()->assertJsonPath('summary.expense', '0.00');
        $this->assertSame($before, DB::table('accounting_journal_lines')->where('journal_id', $journal['id'])->get()->toJson());
        $this->assertDatabaseHas('accounting_audits', ['company_id' => $this->company, 'action' => 'Cash-basis allocation saved', 'subject' => $journal['number']]);
        $this->postJson($url, $this->cashReviewBody())->assertConflict();
        $account = DB::table('accounting_accounts')->find($this->accounts['5110']);
        $this->deleteJson('/accounting/api/accounts/'.$account->id, ['company_id' => $this->company, 'version' => $account->version])->assertUnprocessable();
        $this->postJson($url, $this->cashReviewBody(['version' => 1, 'decision' => 'outside', 'allocations' => [], 'note' => 'Actually repayment of a business loan.']))->assertOk();
        $this->getJson($url.'?company_id='.$this->company)->assertOk()->assertJsonPath('review.version', 2)->assertJsonPath('review.decision', 'outside');
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.expense', '0.00')->assertJsonPath('cash_basis_complete', true);
    }

    public function test_cash_basis_reviews_validate_reconciliation_and_account_selection(): void
    {
        $journal = $this->unallocatedCash();
        $url = '/accounting/api/cash-basis-reviews/'.$journal['id'];
        foreach (['49.99', '0.00', '50.001', '-50.00', '1e2'] as $amount) {
            $this->postJson($url, $this->cashReviewBody(['allocations' => [['account_id' => $this->accounts['5110'], 'amount' => $amount, 'refund' => false]]]))->assertUnprocessable();
        }
        foreach (['5000', '1120'] as $code) {
            $this->postJson($url, $this->cashReviewBody(['allocations' => [['account_id' => $this->accounts[$code], 'amount' => '50.00', 'refund' => false]]]))->assertUnprocessable();
        }
        $this->postJson($url, $this->cashReviewBody(['note' => '     ']))->assertUnprocessable();
        $this->postJson($url, $this->cashReviewBody(['decision' => 'outside']))->assertUnprocessable();
        $this->assertDatabaseMissing('accounting_cash_basis_reviews', ['journal_id' => $journal['id']]);
        $this->postJson($url, $this->cashReviewBody(['outside_amount' => '10.00', 'allocations' => [['account_id' => $this->accounts['5110'], 'amount' => '40.00', 'refund' => false]]]))->assertOk();
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.expense', '40.00')->assertJsonPath('cash_basis_complete', true);
        $direct = $this->create($this->body());
        $this->postJson('/accounting/api/cash-basis-reviews/'.$direct['id'], $this->cashReviewBody())->assertUnprocessable();
    }

    public function test_cash_basis_review_permissions_company_scope_and_closed_periods(): void
    {
        $journal = $this->unallocatedCash();
        $url = '/accounting/api/cash-basis-reviews/'.$journal['id'];
        $user = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer']);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->getJson($url.'?company_id='.$this->company)->assertOk()->assertJsonPath('can_resolve', false);
        $this->postJson($url, $this->cashReviewBody())->assertForbidden();
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'accountant']);
        $this->postJson($url, $this->cashReviewBody())->assertOk();
        $foreign = DB::table('communication_companies')->insertGetId(['name' => 'Another books company']);
        $this->getJson($url.'?company_id='.$foreign)->assertForbidden();
        $this->withSession(['judibas_admin' => true]);
        $this->getJson($url.'?company_id='.$foreign)->assertNotFound();
        DB::table('accounting_books')->where('company_id', $this->company)->update(['locked_through' => now()->toDateString()]);
        $this->getJson($url.'?company_id='.$this->company)->assertOk()->assertJsonPath('can_resolve', false)->assertJsonPath('period_locked', true);
        $this->postJson($url, $this->cashReviewBody(['version' => 1]))->assertUnprocessable();
    }

    public function test_cash_basis_review_reversals_offset_allocations_on_their_own_dates(): void
    {
        $date = now()->subDays(5)->toDateString();
        $journal = $this->unallocatedCash($date);
        $this->postJson('/accounting/api/cash-basis-reviews/'.$journal['id'], $this->cashReviewBody())->assertOk();
        $reversal = $this->postJson('/accounting/api/journals/'.$journal['id'].'/reverse', ['company_id' => $this->company, 'version' => $journal['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Reverse allocated cash movement'])->assertOk()->json('journal');
        $this->report('profit', ['basis' => 'cash', 'to' => now()->subDay()->toDateString()])->assertOk()->assertJsonPath('summary.expense', '50.00')->assertJsonPath('cash_basis_complete', true);
        $this->report('profit', ['basis' => 'cash', 'from' => now()->toDateString()])->assertOk()->assertJsonPath('summary.expense', '-50.00');
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.expense', '0.00')->assertJsonPath('cash_basis_complete', true);
        $this->getJson('/accounting/api/cash-basis-reviews/'.$reversal['id'].'?company_id='.$this->company)->assertOk()->assertJsonPath('journal.id', $journal['id'])->assertJsonPath('review.version', 1);
    }

    public function test_cash_basis_review_supports_split_expenses_and_rejects_artificial_offsetting_amounts(): void
    {
        $journal = $this->unallocatedCash();
        $url = '/accounting/api/cash-basis-reviews/'.$journal['id'];
        $this->postJson($url, $this->cashReviewBody(['allocations' => [
            ['account_id' => $this->accounts['4100'], 'amount' => '10.00', 'refund' => false],
            ['account_id' => $this->accounts['5110'], 'amount' => '60.00', 'refund' => false],
        ]]))->assertUnprocessable();
        $expense = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['5100'], 'code' => '05-01-02', 'name' => 'Travel expense', 'root_type' => 'Expense', 'account_type' => 'Operating Expense']);
        $this->postJson($url, $this->cashReviewBody(['allocations' => [
            ['account_id' => $this->accounts['5110'], 'amount' => '30.00', 'refund' => false],
            ['account_id' => $expense, 'amount' => '20.00', 'refund' => false],
        ]]))->assertOk();
        $report = $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.expense', '50.00')->assertJsonPath('cash_basis_complete', true);
        $this->assertSame('20.00', collect($report->json('rows'))->keyBy('account_id')[$expense]['amount']);
    }

    public function test_cash_basis_review_handles_mixed_gross_income_and_bank_fees(): void
    {
        $journal = $this->create($this->body('100.00', ['lines' => [
            ['account_id' => $this->accounts['1120'], 'debit' => '90.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['5110'], 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $this->accounts['4100'], 'debit' => '0.00', 'credit' => '100.00'],
        ]]));
        $this->postJson('/accounting/api/cash-basis-reviews/'.$journal['id'], $this->cashReviewBody(['allocations' => [
            ['account_id' => $this->accounts['4100'], 'amount' => '100.00', 'refund' => false],
            ['account_id' => $this->accounts['5110'], 'amount' => '10.00', 'refund' => false],
        ]]))->assertOk();
        $this->report('profit', ['basis' => 'cash'])->assertOk()->assertJsonPath('summary.income', '100.00')->assertJsonPath('summary.expense', '10.00')->assertJsonPath('summary.profit', '90.00')->assertJsonPath('cash_basis_complete', true);
    }

    public function test_journal_print_shows_saved_status_and_details_without_posting_a_draft(): void
    {
        $journal = $this->create($this->body('125.50', ['remarks' => '<b>Not HTML</b>']), false);
        $url = '/accounting/journals/'.$journal['id'].'/print?company_id='.$this->company;
        $before = DB::table('accounting_journal_lines')->where('journal_id', $journal['id'])->get()->toJson();
        $this->get($url.'&version='.$journal['version'].'&auto_print=1')->assertOk()->assertSee('Journal Entry')->assertSee($journal['number'])->assertSee('DRAFT - saved for review')->assertSee('125.50')->assertSee('Cash')->assertSee('&lt;b&gt;Not HTML&lt;/b&gt;', false)->assertDontSee('<b>Not HTML</b>', false)->assertSee("window.addEventListener('load'", false);
        $this->assertDatabaseHas('accounting_journals', ['id' => $journal['id'], 'status' => 'draft']);
        $this->assertSame($before, DB::table('accounting_journal_lines')->where('journal_id', $journal['id'])->get()->toJson());
        $this->assertDatabaseHas('accounting_audits', ['company_id' => $this->company, 'action' => 'Journal print opened', 'subject' => $journal['number']]);
        $posted = $this->postJson('/accounting/api/journals/'.$journal['id'].'/post', ['company_id' => $this->company, 'version' => $journal['version']])->assertOk()->json('journal');
        $this->get($url.'&version='.$journal['version'])->assertStatus(409);
        $this->get($url.'&version='.$posted['version'])->assertOk()->assertDontSee('DRAFT - saved for review');
        $this->postJson('/accounting/api/journals/'.$journal['id'].'/reverse', ['company_id' => $this->company, 'version' => $posted['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Incorrect journal'])->assertOk();
        $this->get($url)->assertOk()->assertSee('REVERSED - this entry has been offset');
    }

    public function test_journal_print_checks_company_access_and_allows_assigned_read_only_users(): void
    {
        $journal = $this->create($this->body(), false);
        $user = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer']);
        $this->withSession(['judibas_admin' => false])->actingAs($user);
        $this->get('/accounting/journals/'.$journal['id'].'/print?company_id='.$this->company)->assertOk();
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Private other books']);
        $this->get('/accounting/journals/'.$journal['id'].'/print?company_id='.$other)->assertForbidden();
        $this->withSession(['judibas_admin' => true]);
        $this->get('/accounting/journals/'.$journal['id'].'/print?company_id='.$other)->assertNotFound();
    }

    public function test_contra_moves_cash_to_bank_without_income_and_rejects_invalid_accounts(): void
    {
        $this->create($this->body('50000.00'));
        $body=$this->body('20000.00',['kind'=>'contra','voucher_person'=>'Daily surplus return','lines'=>[
            ['account_id'=>$this->accounts['1110'],'debit'=>'0.00','credit'=>'20000.00'],
            ['account_id'=>$this->accounts['1120'],'debit'=>'20000.00','credit'=>'0.00'],
        ]]);
        $draft=$this->create($body,false);
        $this->assertStringStartsWith('CV-',$draft['number']);
        $this->create($body);
        $rows=collect($this->getJson('/accounting/api/data?company_id='.$this->company)->assertOk()->json('accounts'))->keyBy('id');
        $this->assertSame('30000.00',$rows[$this->accounts['1110']]['balance']);
        $this->assertSame('20000.00',$rows[$this->accounts['1120']]['balance']);
        $this->assertSame('50000.00',$rows[$this->accounts['4100']]['credit']);
        $body['request_key']=(string)Str::uuid();$body['lines'][1]['account_id']=$this->accounts['5110'];
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable();
        $body['request_key']=(string)Str::uuid();$body['lines'][1]['account_id']=$this->accounts['1120'];$body['lines'][0]['credit']='40000.00';$body['lines'][1]['debit']='40000.00';
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable();
    }

    public function test_cash_flow_reconciles_posted_cash_and_excludes_internal_transfers_and_drafts():void
    {
        $date=now()->toDateString();
        $this->create($this->body('1000.00',['posting_date'=>now()->subDay()->toDateString(),'lines'=>[['account_id'=>$this->accounts['1110'],'debit'=>'1000.00','credit'=>'0.00'],['account_id'=>$this->accounts['3100'],'debit'=>'0.00','credit'=>'1000.00']]]));
        $this->create($this->body('100.00'));
        $this->create($this->body('500.00'),false);
        $this->create($this->body('30.00',['lines'=>[['account_id'=>$this->accounts['5110'],'debit'=>'30.00','credit'=>'0.00'],['account_id'=>$this->accounts['1110'],'debit'=>'0.00','credit'=>'30.00']]]));
        $this->create($this->body('50.00',['kind'=>'contra','voucher_person'=>'Internal transfer','lines'=>[['account_id'=>$this->accounts['1120'],'debit'=>'50.00','credit'=>'0.00'],['account_id'=>$this->accounts['1110'],'debit'=>'0.00','credit'=>'50.00']]]));
        $report=$this->getJson('/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'report'=>'cashflow','from'=>$date,'to'=>$date]))->assertOk();
        $report->assertJsonPath('summary.opening','1000.00')->assertJsonPath('summary.inflow','100.00')->assertJsonPath('summary.outflow','30.00')->assertJsonPath('summary.net','70.00')->assertJsonPath('summary.closing','1070.00')->assertJsonPath('activities.operating','70.00')->assertJsonPath('internal_transfers_excluded',1)->assertJsonPath('review_count',0);
        $balances=collect($report->json('accounts'))->sum(fn($a)=>(float)$a['balance']);$this->assertSame(1070.0,$balances);
    }

    public function test_cash_flow_separates_opening_entries_from_trading_cash_flow():void
    {
        $start=DB::table('accounting_books')->where('company_id',$this->company)->value('start_date');
        $this->create($this->body('1000.00',['kind'=>'opening','posting_date'=>$start,'lines'=>[['account_id'=>$this->accounts['1110'],'debit'=>'1000.00','credit'=>'0.00'],['account_id'=>$this->accounts['3100'],'debit'=>'0.00','credit'=>'1000.00']]]));
        $this->getJson('/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'report'=>'cashflow','from'=>$start,'to'=>now()->toDateString()]))->assertOk()->assertJsonPath('summary.inflow','0.00')->assertJsonPath('summary.opening_adjustments','1000.00')->assertJsonPath('summary.closing','1000.00');
    }

    public function test_cash_flow_classifies_capital_fixed_assets_and_marks_ambiguous_cash_for_review():void
    {
        $this->create($this->body('200.00',['lines'=>[['account_id'=>$this->accounts['1110'],'debit'=>'200.00','credit'=>'0.00'],['account_id'=>$this->accounts['3100'],'debit'=>'0.00','credit'=>'200.00']]]));
        foreach(['Fixed Asset'=>'50.00','General'=>'10.00'] as $type=>$amount){
            $parent=DB::table('accounting_accounts')->find($this->accounts['1100']);
            $id=DB::table('accounting_accounts')->insertGetId(['company_id'=>$this->company,'parent_id'=>$parent->id,'code'=>\App\Modules\Accounting\Services\ChartSummary::nextCode($this->company,$parent),'name'=>'Cashflow '.$type,'root_type'=>'Asset','account_type'=>$type,'active'=>true,'is_group'=>false]);
            $this->create($this->body($amount,['lines'=>[['account_id'=>$id,'debit'=>$amount,'credit'=>'0.00'],['account_id'=>$this->accounts['1110'],'debit'=>'0.00','credit'=>$amount]]]));
        }
        $this->getJson('/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'report'=>'cashflow','from'=>now()->toDateString(),'to'=>now()->toDateString()]))->assertOk()->assertJsonPath('activities.financing','200.00')->assertJsonPath('activities.investing','-50.00')->assertJsonPath('activities.review','-10.00')->assertJsonPath('review_count',1)->assertJsonPath('summary.closing','140.00');
    }

    private function body(string $amount = '100.00', array $replace = []): array
    {
        return array_replace(['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'remarks' => 'Cash revenue', 'reference' => 'Test reference', 'lines' => [['account_id' => $this->accounts['1110'], 'debit' => $amount, 'credit' => '0.00'], ['account_id' => $this->accounts['4100'], 'debit' => '0.00', 'credit' => $amount]]], $replace);
    }

    public function test_single_voucher_supports_multiple_salary_lines_and_all_six_types(): void
    {
        // Fund each settlement ledger with a receipt before testing payments.
        foreach (['cash_receipt'=>'1110','bank_receipt'=>'1120'] as $kind=>$code) {
            $j=$this->create($this->body('300.03',['kind'=>$kind,'voucher_person'=>'Demo receipts','lines'=>[
                ['account_id'=>$this->accounts[$code],'debit'=>'300.03','credit'=>'0.00'],
                ['account_id'=>$this->accounts['4100'],'debit'=>'0.00','credit'=>'100.01','description'=>'Customer A'],
                ['account_id'=>$this->accounts['4100'],'debit'=>'0.00','credit'=>'200.02','description'=>'Customer B'],
            ]]));
            $this->assertSame('300.03',$j['total']);
        }
        foreach (['cash_payment'=>'1110','bank_payment'=>'1120','expense'=>'1120'] as $kind=>$code) {
            $j=$this->create($this->body('60.06',['kind'=>$kind,'voucher_person'=>'November salaries','lines'=>[
                ['account_id'=>$this->accounts[$code],'debit'=>'0.00','credit'=>'60.06'],
                ['account_id'=>$this->accounts['5110'],'debit'=>'10.01','credit'=>'0.00','description'=>'Ali salary'],
                ['account_id'=>$this->accounts['5110'],'debit'=>'20.02','credit'=>'0.00','description'=>'Ahmed salary'],
                ['account_id'=>$this->accounts['5110'],'debit'=>'30.03','credit'=>'0.00','description'=>'Sara salary'],
            ]]));
            $this->get('/accounting/vouchers/'.$j['id'].'/print?company_id='.$this->company)->assertOk()->assertSee('Ali salary')->assertSee('Sara salary');
            $this->assertCount(4,$j['lines']);
        }
        $j=$this->create($this->body('2.00',['kind'=>'income','voucher_person'=>'Service income','lines'=>[
            ['account_id'=>$this->accounts['1110'],'debit'=>'2.00','credit'=>'0.00'],
            ['account_id'=>$this->accounts['4100'],'debit'=>'0.00','credit'=>'1.00'],
            ['account_id'=>$this->accounts['4100'],'debit'=>'0.00','credit'=>'1.00'],
        ]]));
        $this->assertStringStartsWith('IV-',$j['number']);
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&vouchers=1')->assertOk()->assertJsonPath('total',6);
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertOk()->assertJsonPath('total',0);
        $this->report('trial')->assertOk()->assertJsonPath('summary.closing_difference','0.00');
    }

    public function test_single_voucher_rejects_wrong_accounts_direction_and_insufficient_bank_funds(): void
    {
        $body=$this->body('100.00',['kind'=>'bank_payment','voucher_person'=>'Salary payment','lines'=>[
            ['account_id'=>$this->accounts['1120'],'debit'=>'0.00','credit'=>'100.00'],
            ['account_id'=>$this->accounts['5110'],'debit'=>'100.00','credit'=>'0.00'],
        ]]);
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable()->assertJsonFragment(['message'=>'Insufficient cash/bank balance on '.now()->toDateString().'. Available: PKR 0.00. Payment: PKR 100.00.']);
        $body['lines'][0]['account_id']=$this->accounts['1110'];
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable();
        $body['kind']='expense';$body['lines'][1]['account_id']=$this->accounts['4100'];
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable();
        $body['kind']='income';
        $this->postJson('/accounting/api/journals',$body)->assertUnprocessable();
    }

    public function test_single_voucher_uses_existing_salary_liability_without_recording_expense_twice(): void
    {
        $liability=$this->postJson('/accounting/api/accounts',['company_id'=>$this->company,'parent_id'=>$this->accounts['2100'],'code'=>'','name'=>'Salary clearing payable','root_type'=>'Liability','account_type'=>'General','is_group'=>false,'active'=>true])->assertOk()->json('account.id');
        $this->create($this->body('100.00',['lines'=>[['account_id'=>$this->accounts['1120'],'debit'=>'100.00','credit'=>'0.00'],['account_id'=>$this->accounts['3100'],'debit'=>'0.00','credit'=>'100.00']]]));
        $this->create($this->body('100.00',['kind'=>'expense','voucher_person'=>'Salary accrual','lines'=>[['account_id'=>$liability,'debit'=>'0.00','credit'=>'100.00'],['account_id'=>$this->accounts['5110'],'debit'=>'100.00','credit'=>'0.00']]]));
        $this->create($this->body('100.00',['kind'=>'bank_payment','voucher_person'=>'Salary settlement','lines'=>[['account_id'=>$this->accounts['1120'],'debit'=>'0.00','credit'=>'100.00'],['account_id'=>$liability,'debit'=>'40.00','credit'=>'0.00','description'=>'Employee A'],['account_id'=>$liability,'debit'=>'60.00','credit'=>'0.00','description'=>'Employee B']]]));
        $this->report('profit')->assertOk()->assertJsonPath('summary.expense','100.00');
        $this->report('ledger',['account_id'=>$liability])->assertOk()->assertJsonPath('closing','0.00');
    }

    private function create(array $body, bool $post = true): array
    {
        $j = $this->postJson('/accounting/api/journals', $body)->assertOk()->json('journal');
        if ($post) {
            $j = $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk()->json('journal');
        }

        return $j;
    }

    private function report(string $type, array $replace = [])
    {
        return $this->getJson('/accounting/api/reports?'.http_build_query(array_replace(['company_id' => $this->company, 'report' => $type, 'from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()], $replace)));
    }

    public function test_draft_has_no_ledger_effect_and_posting_is_balanced_and_idempotent(): void
    {
        $body = $this->body('100.29');
        $j = $this->create($body, false);
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.id', $j['id']);
        $this->postJson('/accounting/api/journals', array_replace($body, ['remarks' => 'Different transaction']))->assertConflict();
        foreach ([1, 2] as $n) {
            $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertOk();
        }
        $this->assertSame(2, DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->count());
        $this->report('trial')->assertOk()->assertJsonPath('summary.debit', '100.29')->assertJsonPath('summary.credit', '100.29')->assertJsonPath('summary.closing_difference', '0.00');
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '100.29');
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '100.29')->assertJsonPath('summary.accumulated_earnings', '100.29')->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_rejects_invalid_precision_unbalanced_negative_both_sides_group_and_control_accounts(): void
    {
        foreach (['-1', '1.001', '1e3', '10000000000.00'] as $amount) {
            $this->postJson('/accounting/api/journals', $this->body($amount))->assertUnprocessable();
        }
        $b = $this->body();$b['lines'][1]['credit'] = '99.99';
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        $b = $this->body();$b['lines'][0]['credit'] = '1.00';
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        foreach (['1000'] as $code) {
            $b = $this->body();$b['lines'][0]['account_id'] = $this->accounts[$code];
            $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('accounting_journals')->where('company_id', $this->company)->count());
        $b = $this->body();$b['lines'][0]['debit'] = ['invalid' => 100];
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
    }

    public function test_general_journals_allow_receivable_and_payable_ledger_accounts(): void
    {
        foreach (['1130','2110'] as $code) {
            $body=$this->body('75.00');
            $body['lines'][0]['account_id']=$this->accounts[$code];
            $party=$this->postJson('/accounting/api/parties',['company_id'=>$this->company,'kind'=>$code==='1130'?'customer':'supplier','name'=>'JV party '.$code,'active'=>true])->assertOk()->json();
            $body['lines'][0]['party_id']=DB::table('accounting_parties')->where('company_id',$this->company)->where('name','JV party '.$code)->value('id');
            $journal=$this->create($body);
            $this->postJson('/accounting/api/journals',$body)->assertOk()->assertJsonPath('journal.id',$journal['id']);
            $this->assertSame('posted',$journal['status']);
            $this->assertDatabaseHas('accounting_journal_lines',['journal_id'=>$journal['id'],'account_id'=>$this->accounts[$code],'debit_minor'=>7500]);
        }
    }

    public function test_draft_versions_prevent_stale_updates_and_posting(): void
    {
        $j = $this->create($this->body(), false);
        $body = array_replace($j, ['remarks' => 'Updated draft']);
        $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.version', 2);
        $this->postJson('/accounting/api/journals', $body)->assertConflict();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertConflict();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 2])->assertOk();
        $this->postJson('/accounting/api/journals', array_replace($body, ['version' => 3]))->assertConflict();
    }

    public function test_reversal_keeps_original_entries_and_correctly_offsets_reports(): void
    {
        $j = $this->create($this->body('250.75'));
        $lineIds = DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->pluck('id')->all();
        $body = ['company_id' => $this->company, 'version' => $j['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Incorrect revenue entry'];
        $r = $this->postJson('/accounting/api/journals/'.$j['id'].'/reverse', $body)->assertOk()->json('journal');
        $this->assertSame($j['id'], $r['reversal_of']);
        $this->assertSame($lineIds, DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->pluck('id')->all());
        $this->assertDatabaseHas('accounting_journals', ['id' => $j['id'], 'status' => 'reversed']);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/reverse', $body)->assertConflict();
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->report('trial')->assertOk()->assertJsonPath('summary.debit', '501.50')->assertJsonPath('summary.credit', '501.50');
        $this->report('ledger')->assertOk()->assertJsonPath('entries.total', 4)->assertJsonPath('closing', '0.00');
    }

    public function test_company_currency_and_closed_period_are_protected(): void
    {
        $j = $this->create($this->body(), false);
        $settings = ['company_id' => $this->company, 'version' => 1, 'currency' => 'PKR', 'approval_required' => false, 'start_date' => now()->subYear()->toDateString(), 'locked_through' => now()->toDateString()];
        $this->postJson('/accounting/api/books', array_replace($settings, ['currency' => 'USD']))->assertUnprocessable();
        $this->postJson('/accounting/api/books', $settings)->assertOk();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertUnprocessable();
        $this->postJson('/accounting/api/journals', $this->body())->assertUnprocessable();
        $this->postJson('/accounting/api/books', array_replace($settings, ['version' => 2, 'locked_through' => null]))->assertUnprocessable();
        $this->postJson('/accounting/api/books', $settings)->assertConflict();
    }

    public function test_opening_entries_and_report_opening_balances_are_correct(): void
    {
        $body = $this->body('1000.00', ['kind' => 'opening', 'posting_date' => now()->subYear()->toDateString()]);
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['lines'][1]['account_id'] = $this->accounts['3100'];
        $this->create($body);
        $this->create($this->body('100.01'));
        $expense = $this->body('20.05');$expense['lines'][0]['account_id'] = $this->accounts['5110'];$expense['lines'][1]['account_id'] = $this->accounts['1110'];
        $this->create($expense);
        $this->report('ledger', ['account_id' => $this->accounts['1110']])->assertOk()->assertJsonPath('opening', '1000.00')->assertJsonPath('debit', '100.01')->assertJsonPath('credit', '20.05')->assertJsonPath('closing', '1079.96')->assertJsonPath('entries.data.1.balance', '1079.96');
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '79.96');
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '1079.96')->assertJsonPath('summary.equity', '1000.00')->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_viewer_and_other_company_cannot_write_or_see_private_entries(): void
    {
        $j = $this->create($this->body());
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Outside company']);
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$other], 'accounting_role' => 'accountant']);
        $this->withSession(['judibas_admin' => false])->actingAs($u);
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 2])->assertForbidden();
        $this->report('trial')->assertForbidden();
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer']);
        $this->report('trial')->assertOk();
        $this->postJson('/accounting/api/journals', $this->body())->assertForbidden();
        $this->getJson('/accounting/api/audit?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 1, 'currency' => 'PKR', 'approval_required' => false, 'start_date' => now()->subYear()->toDateString()])->assertForbidden();
    }

    public function test_reports_export_all_rows_and_ledger_paginates(): void
    {
        for ($n = 0; $n < 16; $n++) {
            $this->create($this->body('0.10'));
        }
        $this->report('ledger')->assertOk()->assertJsonPath('entries.total', 32)->assertJsonCount(30, 'entries.data');
        $this->report('ledger', ['page' => 2])->assertOk()->assertJsonCount(2, 'entries.data');
        $csv = $this->report('ledger', ['page' => 2, 'export' => 'csv'])->assertOk()->streamedContent();
        $this->assertSame(33, count(array_filter(explode("\n", trim($csv)))));
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '1.60');
    }

    public function test_database_prevents_mutating_posted_ledger_lines(): void
    {
        $j = $this->create($this->body());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->update(['debit_minor' => 999]));
    }

    public function test_database_prevents_deleting_posted_document(): void
    {
        $j = $this->create($this->body());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('accounting_journals')->where('id', $j['id'])->delete());
    }

    public function test_referenced_accounts_cannot_be_deleted_or_retyped(): void
    {
        $this->create($this->body(), false);
        $a = DB::table('accounting_accounts')->find($this->accounts['1110']);
        $this->deleteJson('/accounting/api/accounts/'.$a->id, ['company_id' => $this->company, 'version' => $a->version])->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', array_replace((array) $a, ['account_type' => 'Bank']))->assertUnprocessable();
    }

    public function test_minor_unit_calculations_are_exact(): void
    {
        $this->assertSame(10, Ledger::minor('0.1'));
        $this->assertSame('0.30', Ledger::money(Ledger::minor('0.10') + Ledger::minor('0.20')));
        $this->assertSame('-0.01', Ledger::money(-1));
    }
}
