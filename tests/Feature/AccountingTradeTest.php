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
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'approval_required' => false, 'start_date' => now()->subYear()->toDateString()])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        foreach (['1000' => '01', '1100' => '01-01', '1110' => '01-01-01', '1120' => '01-01-02', '1130' => '01-01-03', '2000' => '02', '2100' => '02-01', '2110' => '02-01-01', '3000' => '03', '3100' => '03-01', '4000' => '04', '4100' => '04-01', '5000' => '05', '5100' => '05-01', '5110' => '05-01-01'] as $legacy => $code) { $this->accounts[$legacy] = $this->accounts[$code]; }
        foreach (['customer', 'supplier'] as $kind) {
            $this->postJson('/accounting/api/parties', ['company_id' => $this->company, 'kind' => $kind, 'name' => 'Example '.$kind, 'active' => true])->assertOk();
        }
        $this->customer = DB::table('accounting_parties')->where('company_id', $this->company)->where('kind', 'customer')->value('id');
        $this->supplier = DB::table('accounting_parties')->where('company_id', $this->company)->where('kind', 'supplier')->value('id');
    }

    public function test_contacts_have_unique_managed_accounts_and_invoices_use_their_own_account(): void
    {
        $customer=DB::table('accounting_parties')->find($this->customer);
        $supplier=DB::table('accounting_parties')->find($this->supplier);
        $this->assertNotNull($customer->account_id);
        $this->assertNotSame($customer->account_id,$supplier->account_id);
        $this->assertSame('Receivable',DB::table('accounting_accounts')->find($customer->account_id)->account_type);
        $this->assertSame('Payable',DB::table('accounting_accounts')->find($supplier->account_id)->account_type);
        $invoice=$this->postJson('/accounting/api/invoices',$this->body('sale'))->assertOk()->json('invoice');
        $this->assertSame($customer->account_id,$invoice['control_account_id']);
        $count=DB::table('accounting_accounts')->where('company_id',$this->company)->count();
        DB::transaction(fn()=>\App\Modules\Accounting\Services\PartyAccounts::backfill($this->company));
        $this->assertSame($count,DB::table('accounting_accounts')->where('company_id',$this->company)->count());
        $this->postJson('/accounting/api/parties',['company_id'=>$this->company,'kind'=>'customer','name'=>'Second customer','active'=>true])->assertOk();
        $other=DB::table('accounting_parties')->where('company_id',$this->company)->where('name','Second customer')->first();
        $this->postJson('/accounting/api/invoices',$this->body('sale',['control_account_id'=>$other->account_id]))->assertUnprocessable();
        $this->postJson('/accounting/api/parties',['company_id'=>$this->company,'id'=>$customer->id,'version'=>$customer->version,'kind'=>'customer','name'=>'Renamed customer','active'=>true])->assertOk();
        $this->assertSame('Renamed customer',DB::table('accounting_accounts')->find($customer->account_id)->name);
    }

    public function test_manual_receipt_reconciles_receivables_without_falsely_settling_an_invoice():void
    {
        $invoice=$this->create($this->body());
        $account=(int)DB::table('accounting_parties')->where('id',$this->customer)->value('account_id');
        $j=$this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'cash_receipt','voucher_person'=>'Customer paid partial balance','lines'=>[
            ['account_id'=>$this->accounts['1110'],'debit'=>'100.00','credit'=>'0.00'],
            ['account_id'=>$account,'debit'=>'0.00','credit'=>'100.00']]])->assertOk()->json('journal');
        $query='/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'report'=>'receivables','from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()]);
        $this->getJson($query)->assertOk()->assertJsonPath('summary.outstanding','250.03');
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>$j['version']])->assertOk();
        $this->getJson($query)->assertOk()->assertJsonPath('summary.outstanding','150.03')->assertJsonPath('summary.invoice_outstanding','250.03')->assertJsonPath('summary.unallocated_adjustments','-100.00')->assertJsonPath('rows.0.paid','0.00');
        $this->assertSame(0,DB::table('accounting_payments')->where('invoice_id',$invoice['id'])->count());
    }

    public function test_pdc_creation_cannot_be_granted_to_an_ordinary_finance_user():void
    {
        $invoice=$this->create($this->body());
        $u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>'senior_finance_manager']);
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->postJson('/accounting/api/cheques',$this->cheque($invoice))->assertForbidden();
        $this->assertDatabaseMissing('accounting_cheques',['invoice_id'=>$invoice['id']]);
        $admin=User::factory()->create(['is_active'=>true,'judibas_administrator'=>true]);
        $this->actingAs($admin)->postJson('/accounting/api/cheques',$this->cheque($invoice))->assertForbidden();
        $this->assertDatabaseMissing('accounting_cheques',['invoice_id'=>$invoice['id']]);
    }

    private function cheque(array $invoice,array $changes=[]):array
    {
        return array_replace(['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'invoice_id'=>$invoice['id'],'bank_account_id'=>$this->accounts['1120'],'cheque_number'=>'CH-'.Str::random(10),'drawee_bank'=>'MCB','cheque_date'=>now()->toDateString(),'recorded_date'=>now()->toDateString(),'amount'=>'100.00'],$changes);
    }
    public function test_pdc_registration_clearance_and_bounce_restore_outstanding():void
    {
        $invoice=$this->create($this->body());$body=$this->cheque($invoice);
        $this->postJson('/accounting/api/cheques',$body)->assertOk();
        $this->postJson('/accounting/api/cheques',$body)->assertOk();
        $this->assertSame(0,DB::table('accounting_payments')->where('invoice_id',$invoice['id'])->count());
        $c=DB::table('accounting_cheques')->where('invoice_id',$invoice['id'])->first();
        $action=['company_id'=>$this->company,'version'=>$c->version,'action'=>'clear','date'=>now()->toDateString(),'note'=>'Bank confirmed clearance'];
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',$action)->assertOk();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',$action)->assertConflict();
        $this->assertSame('cleared',DB::table('accounting_cheques')->find($c->id)->status);
        $this->assertSame(1,DB::table('accounting_payments')->where('invoice_id',$invoice['id'])->where('status','posted')->count());
        $this->getJson('/accounting/api/cheques?company_id='.$this->company)->assertOk()->assertJsonPath('summary.cleared.count',1);
        $c=DB::table('accounting_cheques')->find($c->id);
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',array_replace($action,['version'=>$c->version,'action'=>'bounce','note'=>'Returned by bank']))->assertOk();
        $this->assertSame(0,DB::table('accounting_payments')->where('invoice_id',$invoice['id'])->where('status','posted')->count());
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&id='.$invoice['id'])->assertOk()->assertJsonPath('invoice.outstanding',$invoice['total']);
    }
    public function test_pdc_future_date_overallocation_wrong_company_and_issued_funds_checks():void
    {
        $invoice=$this->create($this->body());$body=$this->cheque($invoice,['cheque_date'=>now()->addDays(3)->toDateString(),'amount'=>'200.00']);
        $this->postJson('/accounting/api/cheques',$body)->assertOk();
        $this->postJson('/accounting/api/cheques',$this->cheque($invoice))->assertUnprocessable();
        $c=DB::table('accounting_cheques')->where('invoice_id',$invoice['id'])->first();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$this->company,'version'=>1,'action'=>'clear','date'=>now()->toDateString(),'note'=>'Too early'])->assertUnprocessable();
        $purchase=$this->create($this->body('purchase'));$this->postJson('/accounting/api/cheques',$this->cheque($purchase))->assertOk();
        $issued=DB::table('accounting_cheques')->where('invoice_id',$purchase['id'])->first();
        $this->postJson('/accounting/api/cheques/'.$issued->id.'/actions',['company_id'=>$this->company,'version'=>1,'action'=>'clear','date'=>now()->toDateString(),'note'=>'No bank funds'])->assertUnprocessable();
        $this->assertSame('pending',DB::table('accounting_cheques')->find($issued->id)->status);
    }

    public function test_pdc_company_scope_permissions_and_cancel_preserve_ledger():void
    {
        $invoice=$this->create($this->body());$body=$this->cheque($invoice);
        $other=DB::table('communication_companies')->insertGetId(['name'=>'Other PDC company']);
        $this->postJson('/accounting/api/cheques',array_replace($body,['company_id'=>$other]))->assertUnprocessable();
        $this->postJson('/accounting/api/cheques',$body)->assertOk();
        $c=DB::table('accounting_cheques')->where('invoice_id',$invoice['id'])->first();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$other,'version'=>1,'action'=>'cancel','date'=>now()->toDateString(),'note'=>'Cross company attempt'])->assertNotFound();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$this->company,'version'=>1,'action'=>'cancel','date'=>now()->toDateString(),'note'=>'Customer replaced cheque'])->assertOk();
        $this->assertSame(0,DB::table('accounting_payments')->where('invoice_id',$invoice['id'])->count());
        $u=User::factory()->create(['is_active'=>true]);
        UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>'viewer']);
        $this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->postJson('/accounting/api/cheques',$this->cheque($invoice))->assertForbidden();
        $this->getJson('/accounting/api/cheques?company_id='.$other)->assertForbidden();
    }

    public function test_pdc_period_filters_reconstruct_historical_outstanding_and_clearance():void
    {
        $day=fn($n)=>now()->subDays($n)->toDateString();
        $invoice=$this->create($this->body('sale',['posting_date'=>$day(10)]));
        $this->postJson('/accounting/api/cheques',$this->cheque($invoice,['recorded_date'=>$day(9),'cheque_date'=>$day(8)]))->assertOk();
        $c=DB::table('accounting_cheques')->where('invoice_id',$invoice['id'])->first();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$this->company,'version'=>1,'action'=>'clear','date'=>$day(6),'note'=>'Historical bank clearance'])->assertOk();
        $get=fn($from,$to,$extra=[])=>$this->getJson('/accounting/api/cheques?'.http_build_query(array_merge(['company_id'=>$this->company,'from'=>$from,'to'=>$to],$extra)));
        $get($day(8),$day(7),['direction'=>'received','status'=>'outstanding'])->assertOk()->assertJsonPath('summary.received.count',1)->assertJsonPath('summary.cleared.count',0)->assertJsonPath('rows.total',1)->assertJsonPath('rows.data.0.status','pending')->assertJsonPath('historical',true);
        $get($day(6),$day(5),['status'=>'cleared'])->assertOk()->assertJsonPath('summary.received.count',0)->assertJsonPath('summary.cleared.count',1)->assertJsonPath('rows.total',1)->assertJsonPath('rows.data.0.status','cleared');
        $get($day(4),$day(3),['status'=>'cleared'])->assertOk()->assertJsonPath('summary.cleared.count',0)->assertJsonPath('rows.total',0);
        $c=DB::table('accounting_cheques')->find($c->id);
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$this->company,'version'=>$c->version,'action'=>'bounce','date'=>$day(2),'note'=>'Historical bank return'])->assertOk();
        $get($day(6),$day(5))->assertOk()->assertJsonPath('summary.cleared.count',1)->assertJsonPath('summary.bounced.count',0);
        $get($day(2),$day(0),['status'=>'bounced'])->assertOk()->assertJsonPath('summary.bounced.count',1)->assertJsonPath('rows.total',1);
    }

    public function test_invoice_percentage_and_fixed_discounts_reduce_tax_and_posted_accounts():void
    {
        $parent=DB::table('accounting_accounts')->find($this->accounts['2100']);
        $tax=DB::table('accounting_accounts')->insertGetId(['company_id'=>$this->company,'parent_id'=>$parent->id,'code'=>\App\Modules\Accounting\Services\ChartSummary::nextCode($this->company,$parent),'name'=>'Discount test tax','root_type'=>'Liability','account_type'=>'Tax','active'=>true]);
        $sale=$this->create($this->body('sale',['discount_type'=>'percent','discount_value'=>'10','tax_percent'=>'10','tax_label'=>'Test tax','tax_account_id'=>$tax,'lines'=>[['description'=>'Software service','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'1000']]]));
        $this->assertSame('1000.00',$sale['subtotal']);$this->assertSame('100.00',$sale['discount']);$this->assertSame('900.00',$sale['net']);$this->assertSame('90.00',$sale['tax']);$this->assertSame('990.00',$sale['total']);$this->assertSame('990.00',$sale['outstanding']);
        $this->get('/accounting/invoices/'.$sale['id'].'/print')->assertOk()->assertSee('Discount')->assertSee('100.00');
        $purchase=$this->create($this->body('purchase',['discount_type'=>'fixed','discount_value'=>'100','lines'=>[['description'=>'Outsourced service','account_id'=>$this->accounts['5110'],'quantity'=>'1','rate'=>'1000']]]));
        $this->assertSame('900.00',$purchase['total']);
        $this->profitBasis('accrual')->assertOk()->assertJsonPath('summary.income','900.00')->assertJsonPath('summary.expense','900.00');
    }
    public function test_discount_allocation_rounds_exactly_and_foreign_currency_posts_balanced():void
    {
        $i=$this->create($this->body('sale',['currency'=>'USD','exchange_rate'=>'280','discount_type'=>'fixed','discount_value'=>'1.00','lines'=>[
            ['description'=>'Service A','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'10.01'],
            ['description'=>'Service B','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'20.02'],
        ]]));
        $this->assertSame('1.00',$i['foreign_discount']);$this->assertSame('280.00',$i['discount']);$this->assertSame('29.03',$i['foreign_total']);$this->assertSame('8128.40',$i['total']);
        $this->assertSame(100,array_sum(array_column($i['lines'],'discount_minor')));
        $j=DB::table('accounting_journal_lines')->where('journal_id',$i['journal_id'])->selectRaw('SUM(debit_minor) as debit,SUM(credit_minor) as credit')->first();$this->assertSame($j->debit,$j->credit);
    }
    public function test_discount_handles_zero_net_detail_line_and_rejects_invalid_values():void
    {
        $lines=[['description'=>'Tiny A','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'0.01'],['description'=>'Tiny B','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'0.01']];
        $i=$this->create($this->body('sale',['discount_value'=>'0.01','lines'=>$lines]));$this->assertSame('0.01',$i['total']);
        foreach([['fixed','0.02'],['percent','100'],['percent','101'],['fixed','-1']] as [$type,$value])$this->postJson('/accounting/api/invoices',$this->body('sale',['discount_type'=>$type,'discount_value'=>$value,'lines'=>$lines]))->assertUnprocessable();
    }

    private function catalogueItem(array $replace=[]):array
    {
        return array_replace(['company_id'=>$this->company,'code'=>'DEV-001','name'=>'Web development','kind'=>'service','unit'=>'Project','description'=>'Website development service','sale_rate'=>'1000.00','purchase_rate'=>'700.00','sellable'=>true,'purchasable'=>true,'income_account_id'=>$this->accounts['4100'],'expense_account_id'=>$this->accounts['5110'],'active'=>true],$replace);
    }
    public function test_catalogue_items_are_company_scoped_and_invoice_snapshots_are_preserved():void
    {
        $body=$this->catalogueItem();$this->postJson('/accounting/api/items',$body)->assertOk();
        $item=DB::table('accounting_items')->where('company_id',$this->company)->first();
        $book=DB::table('accounting_books')->where('company_id',$this->company)->first();
        $this->postJson('/accounting/api/books',['company_id'=>$this->company,'version'=>$book->version,'currency'=>'USD','start_date'=>$book->start_date,'approval_required'=>false])->assertUnprocessable();

        $this->getJson('/accounting/api/items?company_id='.$this->company.'&usage=sale')->assertOk()->assertJsonPath('data.0.sale_rate','1000.00');
        $this->postJson('/accounting/api/items',$body)->assertUnprocessable();
        $invoice=$this->create($this->body('sale',['lines'=>[['item_id'=>$item->id,'description'=>'Custom contract description','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'900']]]));
        $this->assertSame('DEV-001',$invoice['lines'][0]['item_code']);$this->assertSame('900.00',$invoice['total']);
        $this->postJson('/accounting/api/items',$this->catalogueItem(['id'=>$item->id,'version'=>1,'name'=>'Renamed service','active'=>false]))->assertOk();
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&id='.$invoice['id'])->assertOk()->assertJsonPath('invoice.lines.0.item_name','Web development');
        $this->postJson('/accounting/api/invoices',$this->body('sale',['lines'=>[['item_id'=>$item->id,'description'=>'Archived','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'900']]]))->assertUnprocessable();
        $this->getJson('/accounting/api/items?company_id='.$this->company.'&usage=sale')->assertOk()->assertJsonPath('total',0);
        $this->postJson('/accounting/api/items',$this->catalogueItem(['id'=>$item->id,'version'=>1]))->assertConflict();
    }
    public function test_items_reject_wrong_account_types_cross_company_and_unauthorised_writes():void
    {
        $this->postJson('/accounting/api/items',$this->catalogueItem(['income_account_id'=>$this->accounts['1110']]))->assertUnprocessable();
        $this->postJson('/accounting/api/items',$this->catalogueItem(['expense_account_id'=>$this->accounts['1130']]))->assertUnprocessable();
        $other=DB::table('communication_companies')->insertGetId(['name'=>'Other item company']);
        $this->postJson('/accounting/api/items',$this->catalogueItem(['company_id'=>$other]))->assertUnprocessable();
        $this->postJson('/accounting/api/items',$this->catalogueItem(['sellable'=>false]))->assertOk();
        $item=DB::table('accounting_items')->where('company_id',$this->company)->first();
        $this->postJson('/accounting/api/invoices',$this->body('sale',['lines'=>[['item_id'=>$item->id,'description'=>'Not sellable','account_id'=>$this->accounts['4100'],'quantity'=>'1','rate'=>'10']]]))->assertUnprocessable();
        $u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>'viewer']);$this->withSession(['judibas_admin'=>false])->actingAs($u);
        $this->postJson('/accounting/api/items',$this->catalogueItem(['code'=>'NO-WRITE']))->assertForbidden();
        $this->getJson('/accounting/api/items?company_id='.$other)->assertForbidden();
    }

    private function profitBasis(string $basis, ?string $from = null, ?string $to = null, array $extra = [])
    {
        return $this->getJson('/accounting/api/reports?'.http_build_query(array_merge(['company_id' => $this->company, 'report' => 'profit', 'basis' => $basis, 'from' => $from ?? now()->subYear()->toDateString(), 'to' => $to ?? now()->toDateString()], $extra)));
    }

    public function test_cash_basis_uses_payment_dates_partial_settlement_tax_and_reversal_dates(): void
    {
        $tax = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['2100'], 'code' => \App\Modules\Accounting\Services\ChartSummary::nextCode($this->company, DB::table('accounting_accounts')->find($this->accounts['2100'])), 'name' => 'Output tax', 'root_type' => 'Liability', 'account_type' => 'Tax']);
        $invoice = $this->create($this->body('sale', ['posting_date' => now()->subDays(20)->toDateString(), 'tax_percent' => '18.00', 'tax_label' => 'Example test rate', 'tax_account_id' => $tax, 'lines' => [['description' => 'Service', 'account_id' => $this->accounts['4100'], 'quantity' => '1', 'rate' => '100.00']]]));
        $this->profitBasis('accrual')->assertOk()->assertJsonPath('basis', 'accrual')->assertJsonPath('summary.income', '100.00');
        $this->profitBasis('cash')->assertOk()->assertJsonPath('basis', 'cash')->assertJsonPath('summary.income', '0.00');
        $invoice = $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', array_replace($this->payment($invoice, '59.00'), ['posting_date' => now()->subDays(5)->toDateString()]))->assertOk()->json('invoice');
        $firstPayment = $invoice['payments'][0];
        $this->profitBasis('cash', null, now()->subDays(10)->toDateString())->assertOk()->assertJsonPath('summary.income', '0.00');
        $this->profitBasis('cash', now()->subDays(6)->toDateString(), now()->subDay()->toDateString())->assertOk()->assertJsonPath('summary.income', '50.00');
        $invoice = $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $this->payment($invoice, '59.00'))->assertOk()->json('invoice');
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.income', '100.00');
        $this->profitBasis('cash', now()->toDateString())->assertOk()->assertJsonPath('summary.income', '50.00');
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/reverse', ['company_id' => $this->company, 'payment_id' => $firstPayment['id'], 'version' => $firstPayment['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Reverse first cash receipt'])->assertOk();
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.income', '50.00');
        $this->profitBasis('cash', now()->subDays(6)->toDateString(), now()->subDay()->toDateString())->assertOk()->assertJsonPath('summary.income', '50.00');
        $this->profitBasis('cash', now()->toDateString())->assertOk()->assertJsonPath('summary.income', '0.00');
        $this->profitBasis('accrual')->assertOk()->assertJsonPath('summary.income', '100.00');
    }

    public function test_cash_basis_allocates_purchase_payment_between_expense_and_asset(): void
    {
        $asset = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['01-02'], 'code' => '01-02-01', 'name' => 'Equipment', 'root_type' => 'Asset', 'account_type' => 'Fixed Asset']);
        $invoice = $this->create($this->body('purchase', ['lines' => [
            ['description' => 'Office costs', 'account_id' => $this->accounts['5110'], 'quantity' => '1', 'rate' => '80.00'],
            ['description' => 'Equipment', 'account_id' => $asset, 'quantity' => '1', 'rate' => '20.00'],
        ]]));
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.expense', '0.00');
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $this->payment($invoice, '50.00'))->assertOk();
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.expense', '40.00')->assertJsonPath('summary.profit', '-40.00');
        $this->profitBasis('accrual')->assertOk()->assertJsonPath('summary.expense', '80.00');
    }

    public function test_cash_basis_rounding_across_tiny_payments_and_full_reversal_has_no_penny_drift(): void
    {
        $income = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['4000'], 'code' => '04-02', 'name' => 'Other services', 'root_type' => 'Income', 'account_type' => 'Sales']);
        $tax = DB::table('accounting_accounts')->insertGetId(['company_id' => $this->company, 'parent_id' => $this->accounts['2100'], 'code' => \App\Modules\Accounting\Services\ChartSummary::nextCode($this->company, DB::table('accounting_accounts')->find($this->accounts['2100'])), 'name' => 'Output tax', 'root_type' => 'Liability', 'account_type' => 'Tax']);
        $invoice = $this->create($this->body('sale', ['tax_percent' => '18.00', 'tax_label' => 'Example', 'tax_account_id' => $tax, 'lines' => [
            ['description' => 'First', 'account_id' => $this->accounts['4100'], 'quantity' => '1', 'rate' => '0.01'],
            ['description' => 'Second', 'account_id' => $income, 'quantity' => '1', 'rate' => '0.02'],
        ]]));
        $this->assertSame('0.04', $invoice['total']);
        for ($n = 0; $n < 4; $n++) $invoice = $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $this->payment($invoice, '0.01'))->assertOk()->json('invoice');
        $report = $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.income', '0.03');
        $rows = collect($report->json('rows'))->keyBy('account_id');
        $this->assertSame('0.01', $rows[$this->accounts['4100']]['amount']);
        $this->assertSame('0.02', $rows[$income]['amount']);
        foreach ($invoice['payments'] as $payment) $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/reverse', ['company_id' => $this->company, 'payment_id' => $payment['id'], 'version' => $payment['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Reverse test settlement'])->assertOk();
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.income', '0.00');
    }

    public function test_cash_basis_large_amounts_remain_exact_and_csv_identifies_the_basis(): void
    {
        $invoice = $this->create($this->body('sale', ['lines' => [['description' => 'Large test service', 'account_id' => $this->accounts['4100'], 'quantity' => '1', 'rate' => '9000000000.01']]]));
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments', $this->payment($invoice, '4500000000.00'))->assertOk();
        $this->profitBasis('cash')->assertOk()->assertJsonPath('summary.income', '4500000000.00');
        $csv = $this->profitBasis('cash', null, null, ['export' => 'csv'])->assertOk()->streamedContent();
        $this->assertStringContainsString('Cash basis', $csv);
        $this->assertStringContainsString('4500000000.00', $csv);
        $this->assertStringNotContainsString('9000000000.01', $csv);
        $this->assertStringContainsString('Accrual basis', $this->profitBasis('accrual', null, null, ['export' => 'csv'])->assertOk()->streamedContent());
        $this->profitBasis('invalid')->assertUnprocessable();
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
