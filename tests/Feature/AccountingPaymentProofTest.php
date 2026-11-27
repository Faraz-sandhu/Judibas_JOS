<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use App\Modules\Accounting\Services\PaymentProofs;
class AccountingPaymentProofTest extends TestCase {
 use DatabaseTransactions;
 private int $company;private array $accounts;
 protected function setUp():void{parent::setUp();Storage::fake('local');$this->withSession(['judibas_admin'=>true]);$this->company=DB::table('communication_companies')->insertGetId(['name'=>'Payment proof fixture']);$this->postJson('/accounting/api/initialize',['company_id'=>$this->company])->assertOk();$this->postJson('/accounting/api/books',['company_id'=>$this->company,'version'=>0,'currency'=>'PKR','start_date'=>now()->subYear()->toDateString(),'approval_required'=>false])->assertOk();$this->accounts=DB::table('accounting_accounts')->where('company_id',$this->company)->pluck('id','code')->all();}
 private function body(string $kind='general',string $amount='10.00'):array{return ['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>$kind,'voucher_person'=>'Proof test payment','remarks'=>'Proof policy fixture','lines'=>[['account_id'=>$this->accounts[$kind==='bank_receipt'?'01-01-02':'01-01-01'],'debit'=>$amount,'credit'=>'0.00'],['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>$amount]]];}
 private function draft(string $kind='general'):array{return $this->postJson('/accounting/api/journals',$this->body($kind))->assertOk()->json('journal');}
 private function attach(array $j){return $this->post('/accounting/api/journals/'.$j['id'].'/payment-proofs',['company_id'=>$this->company,'version'=>$j['version'],'payment_proof'=>UploadedFile::fake()->create('bank-payment.pdf',10,'application/pdf')],['Accept'=>'application/json']);}
 private function postVoucher(array $j){return $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>$j['version']]);}
 public function test_super_admin_cannot_post_crv_or_brv_without_current_payment_proof():void{
  foreach(['cash_receipt','bank_receipt'] as $kind){$j=$this->draft($kind);$this->postVoucher($j)->assertUnprocessable()->assertJsonPath('message','Attach a payment proof for the current saved voucher before forwarding or posting. JV, opening, Income and Expense voucher types are exempt.');$this->assertDatabaseHas('accounting_journals',['id'=>$j['id'],'status'=>'draft']);$this->attach($j)->assertOk();$this->postVoucher($j)->assertOk();}
 }
 public function test_jv_payment_proof_is_optional():void{
  $j=$this->draft();$this->getJson('/accounting/api/journals/'.$j['id'].'/payment-proofs?company_id='.$this->company)->assertOk()->assertJsonPath('required',false)->assertJsonPath('show_on_voucher',false)->assertJsonPath('has_current',false);
  $this->postVoucher($j)->assertOk();$this->assertSame(0,DB::table('accounting_payment_proofs')->where('journal_id',$j['id'])->count());
 }
 public function test_income_and_expense_voucher_types_are_exempt():void{
  $income=$this->draft('income');$this->postVoucher($income)->assertOk();
  $body=$this->body('expense','5.00');$body['lines']=[['account_id'=>$this->accounts['05-01-01'],'debit'=>'5.00','credit'=>'0.00'],['account_id'=>$this->accounts['01-01-01'],'debit'=>'0.00','credit'=>'5.00']];
  $expense=$this->postJson('/accounting/api/journals',$body)->assertOk()->json('journal');$this->postVoucher($expense)->assertOk();$this->assertSame(0,DB::table('accounting_payment_proofs')->where('company_id',$this->company)->count());
 }
 public function test_saved_account_or_amount_changes_invalidate_old_proof():void{
  $j=$this->draft('cash_receipt');$this->attach($j)->assertOk();$body=$j;$body['lines'][0]['debit']='20.00';$body['lines'][1]['credit']='20.00';$new=$this->postJson('/accounting/api/journals',$body)->assertOk()->json('journal');
  $this->postVoucher($new)->assertUnprocessable();$this->attach($j)->assertConflict();$this->attach($new)->assertOk();$this->postVoucher($new)->assertOk();$this->assertSame(2,DB::table('accounting_payment_proofs')->where('journal_id',$j['id'])->count());
 }
 public function test_finance_officer_forwarding_and_final_posting_require_separate_payment_proof():void{
  DB::table('accounting_books')->where('company_id',$this->company)->update(['approval_required'=>true,'approval_types'=>json_encode(['cash_receipt'])]);$j=$this->draft('cash_receipt');
  $this->postJson('/accounting/api/approvals/submit',['company_id'=>$this->company,'type'=>'journal','id'=>$j['id'],'version'=>$j['version'],'note'=>'Please review voucher.'])->assertOk();
  $a=DB::table('accounting_approvals')->where('company_id',$this->company)->where('document_id',$j['id'])->first();
  $this->post('/accounting/api/approvals/'.$a->id.'/signed-copy',['company_id'=>$this->company,'version'=>$a->version,'note'=>'CEO signature attached.','signed_copy'=>UploadedFile::fake()->create('ceo.pdf',10,'application/pdf')],['Accept'=>'application/json'])->assertOk();
  $a=DB::table('accounting_approvals')->find($a->id);$base=['company_id'=>$this->company,'action'=>'approve','note'=>'Checked the voucher.','verified_signed_copy'=>true];
  $this->postJson('/accounting/api/approvals/'.$a->id.'/actions',$base+['version'=>$a->version])->assertOk();$a=DB::table('accounting_approvals')->find($a->id);$this->assertSame('officer_confirm',$a->stage);
  $this->postJson('/accounting/api/approvals/'.$a->id.'/actions',$base+['version'=>$a->version])->assertUnprocessable();$this->assertSame('officer_confirm',DB::table('accounting_approvals')->find($a->id)->stage);
  $this->attach($j)->assertOk();$this->postJson('/accounting/api/approvals/'.$a->id.'/actions',$base+['version'=>$a->version])->assertOk();$a=DB::table('accounting_approvals')->find($a->id);
  $this->postJson('/accounting/api/approvals/'.$a->id.'/actions',array_replace($base,['action'=>'post','version'=>$a->version]))->assertOk();
 }
 public function test_invalid_files_missing_storage_and_removed_proofs_cannot_bypass_posting():void{
  $j=$this->draft('cash_receipt');$url='/accounting/api/journals/'.$j['id'].'/payment-proofs';
  $this->post($url,['company_id'=>$this->company,'version'=>$j['version'],'payment_proof'=>UploadedFile::fake()->create('fake.jpg',1,'text/plain')],['Accept'=>'application/json'])->assertUnprocessable();
  $this->attach($j)->assertOk();$p=DB::table('accounting_payment_proofs')->where('journal_id',$j['id'])->first();Storage::disk('local')->delete($p->path);$this->postVoucher($j)->assertUnprocessable();
  $this->attach($j)->assertOk();$p=DB::table('accounting_payment_proofs')->where('journal_id',$j['id'])->orderByDesc('id')->first();
  $this->postJson('/accounting/api/payment-proofs/'.$p->id.'/remove',['company_id'=>$this->company,'reason'=>'Incorrect payment attachment.'])->assertOk();$this->postVoucher($j)->assertUnprocessable();Storage::disk('local')->assertExists($p->path);
 }
 public function test_proof_preview_requires_authentication_and_correct_company():void{
  $j=$this->draft();$this->attach($j)->assertOk();$p=DB::table('accounting_payment_proofs')->where('journal_id',$j['id'])->first();$this->get('/accounting/api/payment-proofs/'.$p->id.'?company_id='.$this->company)->assertOk();
  $other=DB::table('communication_companies')->insertGetId(['name'=>'Other proof company']);$this->get('/accounting/api/payment-proofs/'.$p->id.'?company_id='.$other)->assertNotFound();
  $this->withSession(['judibas_admin'=>false]);$this->get('/accounting/api/payment-proofs/'.$p->id.'?company_id='.$this->company)->assertForbidden();
 }
}
