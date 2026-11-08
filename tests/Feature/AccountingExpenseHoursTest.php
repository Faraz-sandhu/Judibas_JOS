<?php
namespace Tests\Feature;
use App\Modules\Accounting\Services\ExpenseVoucherHours;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountingExpenseHoursTest extends TestCase
{
 use \Illuminate\Foundation\Testing\DatabaseTransactions;
 protected function tearDown():void { Carbon::setTestNow(); parent::tearDown(); }
 public function test_expense_window_boundaries_and_super_admin_exception():void {
  $request=Request::create('/');$request->setLaravelSession(app('session')->driver());$request->session()->put('judibas_admin',false);
  $book=(object)['expense_open_time'=>'11:00','expense_close_time'=>'20:00'];
  foreach(['10:59:59'=>false,'11:00:00'=>true,'19:59:59'=>true,'20:00:00'=>false,'23:00:00'=>false,'00:00:00'=>false] as $time=>$allowed){
   Carbon::setTestNow(Carbon::parse('2026-11-04 '.$time,'Asia/Karachi'));
   try { ExpenseVoucherHours::enforce($request,$book,'expense'); $this->assertTrue($allowed,$time); }
   catch(HttpException $e){$this->assertFalse($allowed,$time);$this->assertSame(422,$e->getStatusCode());}
  }
  ExpenseVoucherHours::enforce($request,$book,'general');
  $request->session()->put('judibas_admin',true);ExpenseVoucherHours::enforce($request,$book,'expense');
  $this->assertTrue(true);
 }
 public function test_custom_hours_are_used():void {
  $request=Request::create('/');$request->setLaravelSession(app('session')->driver());$request->session()->put('judibas_admin',false);
  Carbon::setTestNow(Carbon::parse('2026-11-04 09:00','Asia/Karachi'));
  ExpenseVoucherHours::enforce($request,(object)['expense_open_time'=>'08:00','expense_close_time'=>'10:00'],'expense');
  $this->assertTrue(true);
 }
 public function test_only_super_admin_can_change_company_hours():void {
  $company=\Illuminate\Support\Facades\DB::table('communication_companies')->insertGetId(['name'=>'Hours test','created_at'=>now(),'updated_at'=>now()]);
  $payload=['company_id'=>$company,'version'=>0,'currency'=>'PKR','start_date'=>now()->toDateString(),'expense_open_time'=>'09:00','expense_close_time'=>'18:00'];
  $this->withSession(['judibas_admin'=>true])->postJson('/accounting/api/books',$payload)->assertOk();
  $user=\App\Models\User::factory()->create(['is_active'=>true]);
  \App\Services\UserProductAccess::sync($user,['product_slugs'=>['accounts'],'company_ids'=>[$company],'accounting_role'=>'manager']);
  $payload['version']=1;$payload['expense_open_time']='08:00';
  $this->withSession(['judibas_admin'=>false])->actingAs($user)->postJson('/accounting/api/books',$payload)->assertForbidden();
  $payload['expense_open_time']='09:00';
  $this->postJson('/accounting/api/books',$payload)->assertOk();
  $payload['version']=2;$payload['expense_close_time']='08:00';
  $this->withSession(['judibas_admin'=>true])->postJson('/accounting/api/books',$payload)->assertUnprocessable();
 }

 public function test_cash_bank_and_general_expense_entries_cannot_bypass_closing_time():void {
  Carbon::setTestNow(Carbon::parse('2026-11-04 17:06:00','Asia/Karachi'));
  $company=\Illuminate\Support\Facades\DB::table('communication_companies')->insertGetId(['name'=>'Expense cutoff test','created_at'=>now(),'updated_at'=>now()]);
  $this->withSession(['judibas_admin'=>true]);
  $this->postJson('/accounting/api/initialize',['company_id'=>$company])->assertOk();
  $this->postJson('/accounting/api/books',['company_id'=>$company,'version'=>0,'currency'=>'PKR','start_date'=>'2026-11-01','expense_open_time'=>'11:00','expense_close_time'=>'17:05'])->assertOk();
  $ids=\Illuminate\Support\Facades\DB::table('accounting_accounts')->where('company_id',$company)->pluck('id','code');
  $user=\App\Models\User::factory()->create(['is_active'=>true]);
  \App\Services\UserProductAccess::sync($user,['product_slugs'=>['accounts'],'company_ids'=>[$company],'accounting_role'=>'manager']);
  $this->withSession(['judibas_admin'=>false])->actingAs($user);
  foreach(['cash_payment','bank_payment','general','expense'] as $kind){
   $settlement=$kind==='bank_payment'?$ids['01-01-02']:$ids['01-01-01'];
   $this->postJson('/accounting/api/journals',['company_id'=>$company,'request_key'=>(string)\Illuminate\Support\Str::uuid(),'posting_date'=>'2026-11-04','kind'=>$kind,'voucher_person'=>'Test employee','lines'=>[['account_id'=>$ids['05-01-01'],'debit'=>'10.00','credit'=>'0.00'],['account_id'=>$settlement,'debit'=>'0.00','credit'=>'10.00']]])
    ->assertUnprocessable()->assertJsonPath('message','Expense vouchers can only be created or saved between 11:00 AM and 5:05 PM (Pakistan time). Contact your Super Admin if needed.');
  }
  $this->assertSame(0,\Illuminate\Support\Facades\DB::table('accounting_journals')->where('company_id',$company)->count());
 }

 public function test_global_policy_applies_to_existing_and_future_companies_and_rejects_stale_settings():void {
  $this->withSession(['judibas_admin'=>true]);
  $create=function($name){$id=\Illuminate\Support\Facades\DB::table('communication_companies')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]);$this->postJson('/accounting/api/books',['company_id'=>$id,'version'=>0,'currency'=>'PKR','start_date'=>now()->toDateString()])->assertOk();return $id;};
  $a=$create('Global A');$b=$create('Global B');
  $this->postJson('/accounting/api/books',['company_id'=>$a,'version'=>1,'currency'=>'PKR','start_date'=>now()->toDateString(),'approval_required'=>true,'approval_types'=>['general'],'expense_open_time'=>'10:00','expense_close_time'=>'17:05'])->assertOk();
  $c=$create('Global new company');
  foreach([$a,$b,$c] as $id){
   $this->getJson('/accounting/api/data?company_id='.$id)->assertOk()->assertJsonPath('book.approval_types',['general'])->assertJsonPath('book.expense_close_time','17:05');
   $this->assertFalse(\App\Modules\Accounting\Services\ApprovalPolicy::required($id,'cash_payment'));
   $this->assertTrue(\App\Modules\Accounting\Services\ApprovalPolicy::required($id,'general'));
  }
  $this->postJson('/accounting/api/books',['company_id'=>$b,'version'=>1,'currency'=>'PKR','start_date'=>now()->toDateString(),'approval_types'=>[]])->assertConflict();
  $this->assertSame('PKR',\Illuminate\Support\Facades\DB::table('accounting_books')->where('company_id',$b)->value('currency'));
 }

}
