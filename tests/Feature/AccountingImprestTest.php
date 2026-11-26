<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\ImprestFunds;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingImprestTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;

    private User $manager;

    private User $officer;

    private array $accounts;

    private object $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
        $this->travelTo(now('Asia/Karachi')->setTime(14, 0));
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Imprest test company', 'created_at' => now(), 'updated_at' => now()]);
        config(['product-catalogue.groups' => [['id' => 'imprest-test', 'slug' => 'accounts', 'name' => 'Accounting', 'planned' => false, 'companies' => ['Imprest test company']]]]);
        $this->manager = $this->member('manager');
        $this->officer = $this->member('junior_finance_officer');
        $this->admin();
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->subMonth()->toDateString(), 'approval_required' => false])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        $j = $this->postJson('/accounting/api/journals', ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'remarks' => 'Company capital', 'lines' => [
            ['account_id' => $this->accounts['01-01-01'], 'debit' => '200000.00', 'credit' => '0.00'], ['account_id' => $this->accounts['03-01'], 'debit' => '0.00', 'credit' => '200000.00'],
        ]])->assertOk()->json('journal');
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk();
        $id = $this->postJson('/accounting/api/imprest/funds', ['company_id' => $this->company, 'user_id' => $this->officer->id, 'parent_id' => $this->accounts['01-01'], 'name' => 'Officer petty cash'])->assertOk()->json('fund_id');
        $this->fund = DB::table('accounting_imprest_funds')->find($id);
    }

    public function test_coa_shows_actual_balances_only_to_super_admin_and_disbursement_amount_to_company_users():void
    {
        $this->fund();
        $url='/accounting/api/data?company_id='.$this->company;
        $chart=$this->getJson($url)->assertOk()->json('accounts');
        $cash=collect($chart)->firstWhere('id',$this->accounts['01-01-01']);
        $this->assertSame('150000.00',$cash['balance']);
        $this->assertSame('50000.00',$cash['disbursement_amount']);
        $this->assertTrue($cash['actual_balance_visible']);
        $this->officer();
        $chart=$this->getJson($url)->assertOk()->json('accounts');
        foreach($chart as $account){
            $this->assertFalse($account['actual_balance_visible']);$this->assertSame('0.00',$account['debit']);$this->assertSame('0.00',$account['credit']);
            if($account['id']===$this->accounts['01-01-01']){$this->assertSame('50000.00',$account['balance']);$this->assertSame('disbursement',$account['balance_basis']);}
            elseif(!$account['display_balance_visible'])$this->assertSame('0.00',$account['balance']);
        }
        $this->assertSame('50000.00',collect($chart)->firstWhere('id',$this->accounts['01-01-01'])['disbursement_amount']);
        $this->actingAs($this->manager)->withSession(['judibas_admin'=>false]);
        $cash=collect($this->getJson($url)->assertOk()->json('accounts'))->firstWhere('id',$this->accounts['01-01-01']);
        $this->assertSame('50000.00',$cash['balance']);$this->assertSame('50000.00',$cash['disbursement_amount']);
        $this->admin();$before=ImprestFunds::balance($this->fund);
        $this->postJson('/accounting/api/imprest/allocate',$this->body('funding','25000.00'))->assertOk();
        $cash=collect($this->getJson($url)->assertOk()->json('accounts'))->firstWhere('id',$this->accounts['01-01-01']);
        $this->assertSame('50000.00',$cash['disbursement_amount']);
        $this->assertSame($before,ImprestFunds::balance($this->fund));
    }

    private function member(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'judibas_administrator' => false]);
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => $role]);

        return $user;
    }

    private function admin(): void
    {
        $this->actingAs($this->manager)->withSession(['judibas_admin' => true]);
    }

    private function officer(): void
    {
        $this->actingAs($this->officer)->withSession(['judibas_admin' => false]);
    }

    private function body(string $type, string $amount = '50000.00'): array
    {
        return ['company_id' => $this->company, 'fund_id' => $this->fund->id, 'account_id' => $type === 'funding' ? $this->accounts['01-01-01'] : $this->accounts['05-01-01'], 'posting_date' => now()->toDateString(), 'request_key' => (string) Str::uuid(), 'person' => 'Test vendor / officer', 'remarks' => 'Petty cash test', 'amount' => $amount];
    }

    private function fund(): array
    {
        $this->admin();
        $j = $this->postJson('/accounting/api/imprest/allocate', $this->body('funding'))->assertOk()->json('journal');

        return $this->postJson('/accounting/api/imprest/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk()->json('journal');
    }

    public function test_advance_moves_cash_to_officer_without_recording_expense_and_reports_balance(): void
    {
        $advance = $this->fund();
        $this->assertStringStartsWith('IAV-', $advance['number']);
        $this->getJson('/accounting/api/imprest?company_id='.$this->company)->assertOk()->assertJsonPath('can_manage', true)->assertJsonCount(2, 'officers');
        $this->assertSame(5000000, ImprestFunds::balance($this->fund));
        foreach (['cash', 'accrual'] as $basis) {
            $this->getJson('/accounting/api/reports?company_id='.$this->company.'&report=profit&basis='.$basis.'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonPath('summary.expense', '0.00');
        }
        $this->getJson('/accounting/api/reports?company_id='.$this->company.'&report=ledger&account_id='.$this->accounts['01-01-01'].'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonPath('closing', '150000.00');
        $this->getJson('/accounting/api/reports?company_id='.$this->company.'&report=trial&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonPath('summary.closing_difference', '0.00');
        $this->officer();
        $data = $this->getJson('/accounting/api/imprest?company_id='.$this->company)->assertOk();
        $data->assertJsonPath('funds.0.available', '50000.00')->assertJsonPath('can_manage', false)->assertJsonPath('can_post', false);
    }

    public function test_draft_reservations_stop_cumulative_overspending_and_retry_is_idempotent(): void
    {
        $this->fund();
        $this->officer();
        $body = $this->body('spending', '40000.00');
        $j = $this->postJson('/accounting/api/imprest/spend', $body)->assertOk()->json('journal');
        $this->postJson('/accounting/api/imprest/spend', $body)->assertOk()->assertJsonPath('journal.id', $j['id']);
        $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '10000.01'))->assertUnprocessable();
        $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '10000.00'))->assertOk();
        $this->getJson('/accounting/api/imprest?company_id='.$this->company)->assertOk()->assertJsonPath('funds.0.available', '0.00')->assertJsonPath('funds.0.reserved', '50000.00')->assertJsonPath('funds.0.balance', '50000.00');
        $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '0.01'))->assertUnprocessable();
        $this->postJson('/accounting/api/imprest/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertForbidden();
    }

    public function test_non_posted_advances_do_not_fund_spending_and_pending_advances_reserve_source(): void
    {
        $this->postJson('/accounting/api/imprest/allocate', $this->body('funding', '150000.00'))->assertOk();
        $this->postJson('/accounting/api/imprest/allocate', $this->body('funding', '50000.01'))->assertUnprocessable();
        $this->officer();
        $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '1.00'))->assertUnprocessable();
    }

    public function test_cancel_releases_reservation_and_cancelled_voucher_cannot_post_or_enter_approval(): void
    {
        $this->fund();
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending'))->assertOk()->json('journal');
        $this->postJson('/accounting/api/imprest/'.$j['id'].'/cancel', ['company_id' => $this->company, 'version' => $j['version']])->assertOk();
        $this->getJson('/accounting/api/imprest?company_id='.$this->company)->assertOk()->assertJsonPath('funds.0.available', '50000.00');
        $this->admin();
        $this->postJson('/accounting/api/imprest/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertUnprocessable();
        $this->getJson('/accounting/api/approvals/document?company_id='.$this->company.'&type=journal&id='.$j['id'])->assertUnprocessable();
    }

    public function test_officer_cannot_bypass_fund_using_other_endpoints_or_another_officers_fund(): void
    {
        $advance = $this->fund();
        $other = $this->member('junior_finance_officer');
        $this->actingAs($other)->withSession(['judibas_admin' => false]);
        $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '1.00'))->assertForbidden();
        $this->officer();
        foreach (['journals', 'invoices', 'parties', 'orders', 'books', 'roles', 'imprest/funds', 'imprest/allocate'] as $path) {
            $this->postJson('/accounting/api/'.$path, ['company_id' => $this->company])->assertForbidden();
        }
        $this->postJson('/accounting/api/roles', ['company_id' => 999999])->assertForbidden();
        $this->getJson('/accounting/api/imprest?company_id=999999')->assertForbidden();
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertOk()->assertJsonPath('total', 1);
    }

    public function test_managed_fund_accounts_cannot_be_used_in_manual_journals_or_retyped(): void
    {
        $this->fund();
        $body = ['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'kind' => 'general', 'posting_date' => now()->toDateString(), 'lines' => [
            ['account_id' => $this->accounts['05-01-01'], 'debit' => '60000.00', 'credit' => '0.00'], ['account_id' => $this->fund->account_id, 'debit' => '0.00', 'credit' => '60000.00'],
        ]];
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', ['company_id' => $this->company, 'id' => $this->fund->account_id, 'version' => 1, 'parent_id' => $this->accounts['01-01'], 'name' => 'Bypass cash', 'root_type' => 'Asset', 'account_type' => 'General', 'is_group' => false, 'active' => true])->assertUnprocessable();
    }

    public function test_expense_posts_through_existing_ledger_and_reversal_restores_officer_balance(): void
    {
        $advance = $this->fund();
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '10000.00'))->assertOk()->json('journal');
        $this->assertStringStartsWith('PCV-', $j['number']);
        $this->admin();
        $posted = $this->postJson('/accounting/api/imprest/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk()->json('journal');
        $this->assertSame(4000000, ImprestFunds::balance($this->fund));
        foreach (['cash', 'accrual'] as $basis) {
            $this->getJson('/accounting/api/reports?company_id='.$this->company.'&report=profit&basis='.$basis.'&from='.now()->startOfMonth()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonPath('summary.expense', '10000.00');
        }
        $this->postJson('/accounting/api/journals/'.$advance['id'].'/reverse', ['company_id' => $this->company, 'version' => $advance['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Cannot take money already spent'])->assertUnprocessable();
        $this->postJson('/accounting/api/journals/'.$posted['id'].'/reverse', ['company_id' => $this->company, 'version' => $posted['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Expense entered in error'])->assertOk();
        $this->assertSame(5000000, ImprestFunds::balance($this->fund));
    }

    public function test_prints_identify_advance_and_petty_cash_and_restricted_officer_cannot_view_other_journals(): void
    {
        $advance = $this->fund();
        $this->get('/accounting/journals/'.$advance['id'].'/print?company_id='.$this->company)->assertOk()->assertSee('Imprest Advance Voucher');
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '100.00'))->assertOk()->json('journal');
        $this->get('/accounting/vouchers/'.$j['id'].'/print?company_id='.$this->company)->assertOk()->assertSee('Petty Cash Payment Voucher');
        $unrelated = DB::table('accounting_journals')->where('company_id', $this->company)->where('remarks', 'Company capital')->value('id');
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&id='.$unrelated)->assertForbidden();
        $this->get('/accounting/journals/'.$unrelated.'/print?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/approvals/submit', ['company_id' => $this->company, 'type' => 'journal', 'id' => $unrelated, 'version' => 2, 'note' => 'Trying to submit someone elses voucher'])->assertStatus(422);
    }

    public function test_fund_assignment_restricts_even_a_previously_powerful_accounting_role(): void
    {
        DB::table('accounting_members')->where('user_id', $this->officer->id)->update(['role' => 'manager']);
        $this->officer();
        $this->getJson('/accounting/api/imprest?company_id='.$this->company)->assertOk()->assertJsonPath('can_manage', false)->assertJsonPath('can_post', false);
        $this->postJson('/accounting/api/journals', ['company_id' => $this->company])->assertForbidden();
        $this->postJson('/accounting/api/roles', ['company_id' => 999999])->assertForbidden();
        $this->getJson('/accounting/api/roles?company_id=999999')->assertForbidden();
    }

    public function test_custom_imprest_only_role_can_view_own_voucher_without_general_journal_permissions(): void
    {
        $this->fund();
        DB::table('accounting_roles')->insert(['slug' => 'imprest-only-test', 'name' => 'Imprest only', 'permissions' => json_encode(['imprest.view', 'imprest.create']), 'protected' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounting_members')->where('user_id', $this->officer->id)->update(['role' => 'imprest-only-test']);
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '20.00'))->assertOk()->json('journal');
        $this->getJson('/accounting/api/imprest/'.$j['id'].'?company_id='.$this->company)->assertOk()->assertJsonPath('journal.id', $j['id']);
        $other = $this->member('junior_finance_officer');
        $this->actingAs($other);
        $this->getJson('/accounting/api/imprest/'.$j['id'].'?company_id='.$this->company)->assertForbidden();
    }

    public function test_full_paper_approval_posts_the_reserved_expense_and_preserves_separation(): void
    {
        Storage::fake('local');
        $this->fund();
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '1000.00'))->assertOk()->json('journal');
        DB::table('accounting_books')->where('company_id', $this->company)->update(['approval_required' => true, 'approval_types' => json_encode(['cash_payment'])]);
        $this->post('/accounting/api/approvals/0/signed-copy', ['company_id' => $this->company, 'version' => $j['version'], 'document_id' => $j['id'], 'initial' => true, 'note' => 'CEO has signed this paper voucher.', 'signed_copy' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $approval = DB::table('accounting_approvals')->where('document_id', $j['id'])->where('document_type', 'journal')->first();
        $this->postJson('/accounting/api/approvals/'.$approval->id.'/actions', ['company_id' => $this->company, 'version' => $approval->version, 'action' => 'post', 'note' => 'Trying to post my own expense', 'verified_signed_copy' => true])->assertForbidden();
        $this->actingAs($this->manager)->withSession(['judibas_admin' => false]);
        $head=DB::table('communication_companies')->where('name','Judibas GOC')->value('id')??DB::table('communication_companies')->insertGetId(['name'=>'Judibas GOC']);
        UserProductAccess::sync($this->manager,['product_slugs'=>['accounts'],'company_ids'=>[$this->company,$head],'accounting_role'=>'manager']);
        $this->postJson('/accounting/api/approvals/'.$approval->id.'/actions', ['company_id' => $this->company, 'version' => $approval->version, 'action' => 'approve', 'note' => 'Signed copy verified by head-office finance manager.', 'verified_signed_copy' => true])->assertOk();
        $approval=DB::table('accounting_approvals')->find($approval->id);
        $this->postJson('/accounting/api/approvals/'.$approval->id.'/actions',['company_id'=>$this->company,'version'=>$approval->version,'action'=>'approve','note'=>'Manager cannot confirm their own review.'])->assertForbidden();
        $headOfficer=User::factory()->create(['is_active'=>true]);
        UserProductAccess::sync($headOfficer,['product_slugs'=>['accounts'],'company_ids'=>[$this->company,$head],'accounting_role'=>'finance_officer']);
        $this->actingAs($headOfficer);
        $this->postJson('/accounting/api/approvals/'.$approval->id.'/actions',['company_id'=>$this->company,'version'=>$approval->version,'action'=>'approve','note'=>'Judibas GOC Finance Officer confirmed the reviewed voucher.'])->assertOk();
        $this->admin();$approval=DB::table('accounting_approvals')->find($approval->id);
        $this->postJson('/accounting/api/approvals/'.$approval->id.'/actions',['company_id'=>$this->company,'version'=>$approval->version,'action'=>'post','note'=>'Super Admin approves final posting.','verified_signed_copy'=>true])->assertOk();
        $this->assertSame(4900000, ImprestFunds::balance($this->fund));
        $this->assertSame(0, ImprestFunds::reserved($this->fund));
    }

    public function test_approval_requirement_is_not_bypassed_by_imprest_posting(): void
    {
        $this->fund();
        $this->officer();
        $j = $this->postJson('/accounting/api/imprest/spend', $this->body('spending', '100.00'))->assertOk()->json('journal');
        DB::table('accounting_books')->where('company_id', $this->company)->update(['approval_required' => true, 'approval_types' => json_encode(['cash_payment'])]);
        $this->admin();
        $this->postJson('/accounting/api/imprest/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertUnprocessable();
        $this->officer();
        $this->postJson('/accounting/api/approvals/submit',['company_id' => $this->company, 'type' => 'journal', 'id' => $j['id'], 'version' => $j['version'], 'note' => 'Please review this expense'])->assertOk();
        $this->postJson('/accounting/api/imprest/'.$j['id'].'/cancel',['company_id' => $this->company, 'version' => $j['version']])->assertConflict();
    }
}
