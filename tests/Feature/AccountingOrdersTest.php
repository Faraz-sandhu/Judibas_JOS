<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Services\UserProductAccess;
class AccountingOrdersTest extends TestCase {
 use DatabaseTransactions;
 private array $companies=[];
 protected function setUp():void{parent::setUp();$this->withoutVite();$this->withSession(['judibas_admin'=>true]);foreach(['Order Seller','Order Buyer']as$name){$id=DB::table('communication_companies')->insertGetId(['name'=>$name]);$this->companies[]=$id;$this->postJson('/accounting/api/initialize',['company_id'=>$id])->assertOk();$this->postJson('/accounting/api/books',['company_id'=>$id,'version'=>0,'currency'=>'PKR','approval_required'=>false,'start_date'=>now()->subYear()->toDateString()])->assertOk();}}
 private function payload():array{return ['seller_company_id'=>$this->companies[0],'buyer_company_id'=>$this->companies[1],'request_key'=>(string)Str::uuid(),'order_date'=>now()->toDateString(),'due_date'=>now()->addWeek()->toDateString(),'terms'=>'Service agreement','lines'=>[['description'=>'Website work','quantity'=>'1','quoted_rate'=>'200000','rate'=>'180000']]];}
 private function conversion():array{$a=DB::table('accounting_accounts')->where('company_id',$this->companies[0])->get();$b=DB::table('accounting_accounts')->where('company_id',$this->companies[1])->get();return ['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>2,'posting_date'=>now()->toDateString(),'due_date'=>now()->addWeek()->toDateString(),'receivable_account_id'=>$a->firstWhere('account_type','Receivable')->id,'income_account_id'=>$a->first(fn($a)=>$a->root_type==='Income'&&!$a->is_group)->id,'payable_account_id'=>$b->firstWhere('account_type','Payable')->id,'cost_account_id'=>$b->first(fn($a)=>$a->root_type==='Expense'&&!$a->is_group)->id];}
 public function test_pair_contacts_idempotency_conversion_and_existing_posting():void{
  $v=$this->payload();$id=$this->postJson('/accounting/api/orders',$v)->assertOk()->json('id');$this->postJson('/accounting/api/orders',$v)->assertOk()->assertJsonPath('id',$id);$this->assertSame(1,DB::table('accounting_intercompany_orders')->where('request_key',$v['request_key'])->count());
  $this->assertDatabaseHas('accounting_parties',['company_id'=>$this->companies[0],'kind'=>'customer','name'=>'Order Buyer']);$this->assertDatabaseHas('accounting_parties',['company_id'=>$this->companies[1],'kind'=>'supplier','name'=>'Order Seller']);
  $this->getJson('/accounting/api/orders?company_id='.$this->companies[0].'&kind=sale')->assertOk()->assertJsonPath('data.0.total','180000.00')->assertJsonPath('data.0.discount','20000.00');
  $before=DB::table('accounting_journals')->count();$this->postJson("/accounting/api/orders/$id/actions",['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>1,'action'=>'confirm'])->assertOk();$body=$this->conversion();$this->postJson("/accounting/api/orders/$id/invoices",$body)->assertOk();$this->postJson("/accounting/api/orders/$id/invoices",$body)->assertOk();$order=DB::table('accounting_intercompany_orders')->find($id);$this->assertSame($before,DB::table('accounting_journals')->count());
  foreach([[$order->sales_invoice_id,$this->companies[0]],[$order->purchase_invoice_id,$this->companies[1]]]as[$invoice,$company]){$this->assertDatabaseHas('accounting_invoices',['id'=>$invoice,'company_id'=>$company,'status'=>'draft','total_minor'=>18000000]);$this->postJson("/accounting/api/invoices/$invoice/post",['company_id'=>$company,'version'=>1])->assertOk();}
  $this->assertSame($before+2,DB::table('accounting_journals')->count());
  $this->postJson("/accounting/api/orders/$id/actions",['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>3,'action'=>'cancel'])->assertUnprocessable();
 }
 public function test_invalid_mapping_rolls_back_both_invoices_and_access_is_scoped():void{
  $id=$this->postJson('/accounting/api/orders',$this->payload())->assertOk()->json('id');$this->postJson("/accounting/api/orders/$id/actions",['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>1,'action'=>'confirm'])->assertOk();$v=$this->conversion();$v['cost_account_id']=$v['income_account_id'];$before=DB::table('accounting_invoices')->count();$this->postJson("/accounting/api/orders/$id/invoices",$v)->assertUnprocessable();$this->assertSame($before,DB::table('accounting_invoices')->count());
  $u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->companies[1]],'accounting_role'=>'manager']);$this->withSession(['judibas_admin'=>false])->actingAs($u);
  $this->getJson('/accounting/api/orders?company_id='.$this->companies[1].'&kind=purchase')->assertOk();$this->getJson('/accounting/api/orders?company_id='.$this->companies[0].'&kind=sale')->assertForbidden();$this->postJson('/accounting/api/orders',$this->payload())->assertForbidden();
  $this->postJson("/accounting/api/orders/$id/actions",['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>2,'action'=>'cancel'])->assertForbidden();
 }
 public function test_amount_validation_stale_versions_and_request_identity():void{
  $v=$this->payload();$id=$this->postJson('/accounting/api/orders',$v)->assertOk()->json('id');$v['lines'][0]['rate']='170000';$this->postJson('/accounting/api/orders',$v)->assertStatus(409);$v['request_key']=(string)Str::uuid();$v['buyer_company_id']=$v['seller_company_id'];$this->postJson('/accounting/api/orders',$v)->assertUnprocessable();
  $this->postJson("/accounting/api/orders/$id/actions",['company_id'=>$this->companies[1],'kind'=>'purchase','version'=>9,'action'=>'confirm'])->assertStatus(409);
 }
}
