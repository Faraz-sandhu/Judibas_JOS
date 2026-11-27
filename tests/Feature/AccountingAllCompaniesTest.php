<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\UserProductAccess;
class AccountingAllCompaniesTest extends TestCase {
 use DatabaseTransactions;
    use \Tests\Concerns\UploadsAccountingProofFixtures;
 private array $companies=[];
 protected function setUp():void {
  parent::setUp();$this->withSession(['judibas_admin'=>true]);
  $groups=config('product-catalogue.groups');foreach($groups as &$group)if($group['slug']==='accounts')$group['companies']=['All-company test Alpha','All-company test Beta','All-company test Gamma'];unset($group);config(['product-catalogue.groups'=>$groups]);
  foreach(['Alpha','Beta'] as $name){
   $id=DB::table('communication_companies')->insertGetId(['name'=>'All-company test '.$name,'created_at'=>now(),'updated_at'=>now()]);$this->companies[]=$id;
   $this->postJson('/accounting/api/initialize',['company_id'=>$id])->assertOk();
   $this->postJson('/accounting/api/books',['company_id'=>$id,'version'=>0,'currency'=>$name==='Alpha'?'PKR':'USD','start_date'=>now()->startOfMonth()->toDateString(),'approval_required'=>false])->assertOk();
   $ids=DB::table('accounting_accounts')->where('company_id',$id)->pluck('id','code');
   $j=$this->postJson('/accounting/api/journals',['company_id'=>$id,'request_key'=>(string)Str::uuid(),'kind'=>'general','posting_date'=>now()->toDateString(),'remarks'=>'All-company test funding','lines'=>[['account_id'=>$ids['01-01-01'],'debit'=>'100.00','credit'=>'0.00'],['account_id'=>$ids['03-01'],'debit'=>'0.00','credit'=>'100.00']]])->assertOk()->json('journal');
   if($name==='Alpha')$this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$id,'version'=>1])->assertOk();
  }
 }
 private function url(string $section):string{return '/accounting/api/all-companies?'.http_build_query(['section'=>$section,'from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString(),'search'=>'All-company test']);}
 public function test_admin_all_option_and_cross_company_records_keep_company_identity():void {
  $this->getJson('/accounting/api/data?company_id=all')->assertOk()->assertJsonPath('all_companies',true)->assertJsonPath('company_id',null);
  $r=$this->getJson($this->url('journals'))->assertOk()->assertJsonPath('records.total',2)->json('records.data');
  $this->assertEqualsCanonicalizing($this->companies,array_column($r,'company_id'));
  foreach($r as $row){$this->assertSame('100.00',$row['amount']);$this->assertStringContainsString(\App\Services\CompanyWorkspace::url('accounting',$row['company_id']).'?entry_id='.$row['id'],$row['url']);}
  $this->postJson('/accounting/api/journals',['company_id'=>'all'])->assertUnprocessable();
 }
 public function test_reports_use_existing_calculations_without_mixing_currencies_or_drafts():void {
  $totals=$this->getJson($this->url('balance'))->assertOk()->assertJsonPath('mode','combined')->json('totals');
  $totals=collect($totals)->keyBy('currency');$this->assertSame('100.00',$totals['PKR']['summary']['assets']);$this->assertSame('0.00',$totals['USD']['summary']['assets']);
  foreach(['overview','books','chart','ledger','trial','profit','receivables','payables','customers','suppliers','sales','purchases','single-vouchers','cash-receipts','cash-payments','audit','approvals'] as $section)$this->getJson($this->url($section))->assertOk();
 }
 public function test_product_managers_cannot_request_other_company_data_or_all_mode():void {
  $u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->companies[0]],'accounting_role'=>'manager']);
  $this->withSession(['judibas_admin'=>false])->actingAs($u);
  $this->getJson('/accounting/api/data?company_id='.$this->companies[0])->assertOk()->assertJsonPath('can_view_all',false);
  $this->getJson('/accounting/api/data?choose=1')->assertOk()->assertJsonPath('can_view_all',false)->assertJsonCount(1,'companies');
  $this->getJson('/accounting/api/data?company_id=all')->assertForbidden();$this->getJson($this->url('journals'))->assertForbidden();
  $this->getJson('/accounting/api/data?company_id='.$this->companies[1])->assertForbidden();
 }
 public function test_platform_admin_has_all_company_access():void {
  $u=User::factory()->create(['is_active'=>true,'judibas_administrator'=>true]);$this->withSession(['judibas_admin'=>false])->actingAs($u);
  $this->getJson('/accounting/api/data?company_id=all')->assertOk();$this->getJson($this->url('journals'))->assertOk()->assertJsonPath('records.total',2);
 }
 public function test_combined_balances_include_all_ledger_pages_and_only_merge_matching_accounts():void {
  $id=DB::table('communication_companies')->insertGetId(['name'=>'All-company test Gamma','created_at'=>now(),'updated_at'=>now()]);
  $this->postJson('/accounting/api/initialize',['company_id'=>$id])->assertOk();$this->postJson('/accounting/api/books',['company_id'=>$id,'version'=>0,'currency'=>'PKR','start_date'=>now()->startOfMonth()->toDateString(),'approval_required'=>false])->assertOk();
  $ids=DB::table('accounting_accounts')->where('company_id',$id)->pluck('id','code');
  for($i=0;$i<16;$i++){
   $j=$this->postJson('/accounting/api/journals',['company_id'=>$id,'request_key'=>(string)Str::uuid(),'kind'=>'general','posting_date'=>now()->toDateString(),'lines'=>[['account_id'=>$ids['01-01-01'],'debit'=>'0.01','credit'=>'0.00'],['account_id'=>$ids['03-01'],'debit'=>'0.00','credit'=>'0.01']]])->assertOk()->json('journal');
   $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$id,'version'=>1])->assertOk();
  }
  $ledger=$this->getJson($this->url('ledger'))->assertOk()->assertJsonPath('records.total',34)->json();
  $pkr=collect($ledger['totals'])->firstWhere('currency','PKR');$this->assertSame('100.16',$pkr['summary']['debit']);$this->assertSame('100.16',$pkr['summary']['credit']);
  $this->getJson($this->url('ledger').'&page=2')->assertOk()->assertJsonCount(14,'records.data')->assertJsonPath('records.total',34);
  $chart=$this->getJson($this->url('chart'))->assertOk()->json('records.data');
  $cash=collect($chart)->first(fn($a)=>$a['currency']==='PKR'&&$a['code']==='01-01-01');$this->assertSame('100.16',$cash['balance']);$this->assertCount(2,$cash['contributors']);$parent=collect($chart)->firstWhere('key',$cash['parent_key']);$this->assertSame('Current Assets',$parent['name']);
  $totals=$this->getJson($this->url('trial'))->assertOk()->json('totals');$pkr=collect($totals)->firstWhere('currency','PKR');$this->assertSame('100.16',$pkr['summary']['closing_debit']);$this->assertSame('100.16',$pkr['summary']['closing_credit']);
  DB::table('accounting_accounts')->where('id',$ids['01-01-01'])->update(['name'=>'Different cash purpose']);
  $chart=$this->getJson($this->url('chart'))->assertOk()->json('records.data');$cash=collect($chart)->filter(fn($a)=>$a['currency']==='PKR'&&$a['code']==='01-01-01');$this->assertCount(2,$cash);
  foreach($cash as $a)$this->assertCount(1,$a['contributors']);
 }
 public function test_company_chooser_discovery_does_not_select_or_expose_a_company_ledger():void {
  $this->getJson('/accounting/api/data?choose=1')->assertOk()->assertJsonPath('company_id',null)->assertJsonPath('book',null)->assertJsonCount(0,'accounts')->assertJsonPath('can_view_all',true);
 }

 public function test_combined_aging_includes_all_source_pages_and_cash_accrual_remain_distinct():void {
  $id=$this->companies[0];$ids=DB::table('accounting_accounts')->where('company_id',$id)->pluck('id','code');
  $party=DB::table('accounting_parties')->insertGetId(['company_id'=>$id,'kind'=>'customer','name'=>'Combined aging customer','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  for($i=0;$i<31;$i++){
   $invoice=$this->postJson('/accounting/api/invoices',['company_id'=>$id,'request_key'=>(string)Str::uuid(),'kind'=>'sale','party_id'=>$party,'control_account_id'=>$ids['01-01-03'],'posting_date'=>now()->toDateString(),'due_date'=>now()->toDateString(),'tax_percent'=>'0.00','lines'=>[['description'=>'Tiny invoice','account_id'=>$ids['04-01'],'quantity'=>'1.00','rate'=>'0.01']]])->assertOk()->json('invoice');
   $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/post',['company_id'=>$id,'version'=>1])->assertOk();
  }
  $aging=$this->getJson($this->url('receivables'))->assertOk()->assertJsonPath('records.total',31)->json();$pkr=collect($aging['totals'])->firstWhere('currency','PKR');$this->assertSame('0.31',$pkr['summary']['outstanding']);
  $this->getJson($this->url('receivables').'&page=2')->assertOk()->assertJsonCount(11,'records.data');
  $accrual=$this->getJson($this->url('profit').'&basis=accrual')->assertOk()->json('totals');$cash=$this->getJson($this->url('profit').'&basis=cash')->assertOk()->json('totals');
  $this->assertSame('0.31',collect($accrual)->firstWhere('currency','PKR')['summary']['income']);$this->assertSame('0.00',collect($cash)->firstWhere('currency','PKR')['summary']['income']);
 }

 public function test_regular_user_with_three_assigned_companies_only_discovers_those_three():void {
  $third=DB::table('communication_companies')->insertGetId(['name'=>'Assigned third company','created_at'=>now(),'updated_at'=>now()]);
  $other=DB::table('communication_companies')->insertGetId(['name'=>'Unassigned fourth company','created_at'=>now(),'updated_at'=>now()]);
  $assigned=[$this->companies[0],$this->companies[1],$third];
  $user=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($user,['product_slugs'=>['accounts'],'company_ids'=>$assigned,'accounting_role'=>'manager']);
  $this->withSession(['judibas_admin'=>false])->actingAs($user);
  $this->get('/products/accounts')->assertOk()->assertViewHas('portal',fn($portal)=>$portal['product']['slug']==='accounts');
  $list=$this->getJson('/accounting/api/data?choose=1')->assertOk()->assertJsonPath('can_view_all',false)->assertJsonCount(3,'companies')->json('companies');
  $this->assertEqualsCanonicalizing($assigned,array_column($list,'id'));
  $this->getJson('/accounting/api/data?company_id='.$other)->assertForbidden();$this->getJson('/accounting/api/data?company_id=all')->assertForbidden();
 }

}
