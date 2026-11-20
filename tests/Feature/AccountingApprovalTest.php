<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingApprovalTest extends TestCase
{
    use DatabaseTransactions;
    private int $company;
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        config(['product-catalogue.groups'=>[...config('product-catalogue.groups'),['id'=>'accounting-test','slug'=>'accounts','name'=>'Test accounting','planned'=>false,'description'=>'Test fixture','companies'=>['Approval test']]]]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->company = DB::table('communication_companies')->insertGetId(['name'=>'Approval test', 'created_at'=>now(), 'updated_at'=>now()]);
        $this->withSession(['judibas_admin'=>true]);
        $this->postJson('/accounting/api/initialize', ['company_id'=>$this->company])->assertOk();
        $this->postJson('/accounting/api/books', ['company_id'=>$this->company,'version'=>0,'currency'=>'PKR','start_date'=>now()->subYear()->toDateString(),'approval_required'=>true,'approval_types'=>array_keys(\App\Modules\Accounting\Services\ApprovalPolicy::TYPES)])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id',$this->company)->pluck('id','code')->all();
    }

    private function member(string $role): User
    {
        $u=User::factory()->create(['is_active'=>true]);
        UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>$role]);
        return $u;
    }
    private function asUser(User $u): void { $this->withSession(['judibas_admin'=>false])->actingAs($u); }
    private function draft(): array
    {
        return $this->postJson('/accounting/api/journals', ['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'general','remarks'=>'Approval test','lines'=>[['account_id'=>$this->accounts['01-01-01'],'debit'=>'100.00','credit'=>'0.00'],['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>'100.00']]])->assertOk()->json('journal');
    }
    private function submit(array $j): object
    {
        $this->postJson('/accounting/api/approvals/submit',['company_id'=>$this->company,'type'=>'journal','id'=>$j['id'],'version'=>$j['version'],'note'=>'Please review this document.'])->assertOk();
        return DB::table('accounting_approvals')->where('document_type','journal')->where('document_id',$j['id'])->first();
    }
    private function decision(object $a,string $action='approve',bool $verify=true)
    {
        return $this->postJson('/accounting/api/approvals/'.$a->id.'/actions',['company_id'=>$this->company,'version'=>$a->version,'action'=>$action,'note'=>'Checked and confirmed this decision.']+($verify&&$a->workflow_mode==='paper'&&$action==='post'?['verified_signed_copy'=>true]:[]));
    }
    private function latest(object $a): object { return DB::table('accounting_approvals')->find($a->id); }

    private function attach(object $a)
    {
        return $this->post('/accounting/api/approvals/'.$a->id.'/signed-copy',['company_id'=>$this->company,'version'=>$a->version,'note'=>'CEO stamped and signed this paper voucher.','signed_copy'=>\Illuminate\Http\UploadedFile::fake()->create('ceo-signed.pdf',10,'application/pdf')],['Accept'=>'application/json']);
    }

    public function test_paper_workflow_requires_signed_copy_and_independent_head_verification(): void
    {
        $maker=$this->member('finance_officer');$manager=$this->member('senior_finance_manager');
        $this->asUser($maker);$j=$this->draft();$a=$this->submit($j);
        $this->assertSame('paper_ceo',$a->stage);
        $this->asUser($manager);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>$j['version']])->assertUnprocessable();
        $this->decision($a,'post')->assertForbidden();
        $this->asUser($maker);$this->attach($a)->assertOk();$a=$this->latest($a);
        $this->assertSame('manager_review',$a->stage);
        $this->assertSame('draft',DB::table('accounting_journals')->find($j['id'])->status);
        $this->decision($a,'post')->assertForbidden();
        $stale=$a;
        $this->attach($a)->assertOk();$a=$this->latest($a);
        $this->attach($stale)->assertConflict();
        $this->assertSame(2,DB::table('accounting_signed_copies')->where('approval_id',$a->id)->count());
        $this->asUser($manager);
        $this->decision($a,'post',false)->assertUnprocessable();
        $this->decision($a,'post')->assertOk();
        $posted=$this->latest($a);
        $this->assertSame('posted',$posted->stage);
        $this->assertNotNull($posted->verified_copy_id);
        $this->assertSame($manager->id,$posted->verified_by);
        $this->assertSame('posted',DB::table('accounting_journals')->find($j['id'])->status);
        $this->assertSame(4,DB::table('accounting_approval_events')->where('approval_id',$a->id)->count());
        $this->decision($posted,'post')->assertConflict();
    }

    public function test_return_unlocks_document_and_resubmission_starts_from_finance(): void
    {
        $maker=$this->member('finance_officer');$finance=$this->member('finance_officer');
        $this->asUser($maker);$j=$this->draft();$a=$this->submit($j);
        $body=array_merge($j,['remarks'=>'Corrected narration']);
        $this->postJson('/accounting/api/journals',$body)->assertConflict();
        $this->asUser($finance);$this->decision($a,'return')->assertOk();
        $this->asUser($maker);$j=$this->postJson('/accounting/api/journals',$body)->assertOk()->json('journal');
        $a=$this->submit($j);$this->assertSame('paper_ceo',$a->stage);$this->assertSame($j['version'],$a->document_version);
    }

    public function test_custom_roles_enforce_actions_and_deactivation_immediately(): void
    {
        $role=$this->postJson('/accounting/api/roles',['name'=>'Invoice read only','description'=>'Restricted reviewer','active'=>true,'permissions'=>['sales.view']])->assertOk()->json('role');
        $user=$this->member($role['slug']);$this->asUser($user);
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&kind=sale')->assertOk();
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&kind=purchase')->assertForbidden();
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertForbidden();
        $this->getJson('/accounting/api/roles')->assertForbidden();
        $this->postJson('/accounting/api/initialize',['company_id'=>$this->company])->assertForbidden();
        DB::table('accounting_roles')->where('id',$role['id'])->update(['active'=>false]);
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&kind=sale')->assertForbidden();
    }

    public function test_role_permissions_validation_and_protected_templates(): void
    {
        $manager=DB::table('accounting_roles')->where('slug','manager')->first();
        $this->postJson('/accounting/api/roles',['id'=>$manager->id,'version'=>1,'name'=>'Alter manager','active'=>true,'permissions'=>[]])->assertUnprocessable();
        $this->postJson('/accounting/api/roles',['name'=>'Invalid','active'=>true,'permissions'=>['journals.post']])->assertUnprocessable();
        $this->postJson('/accounting/api/roles',['name'=>'Unknown','active'=>true,'permissions'=>['unknown.permission']])->assertUnprocessable();
        $this->asUser($this->member('finance_officer'));
        $this->postJson('/accounting/api/roles',['name'=>'Escalated','active'=>true,'permissions'=>['roles.view','roles.manage']])->assertForbidden();
    }

    public function test_pending_approval_is_preserved_after_global_policy_change_and_company_isolation(): void
    {
        $j=$this->draft();$a=$this->submit($j);
        $book=DB::table('accounting_books')->where('company_id',$this->company)->first();
        $this->postJson('/accounting/api/books',['company_id'=>$this->company,'version'=>$book->version,'currency'=>'PKR','start_date'=>$book->start_date,'approval_required'=>false])->assertOk();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>$j['version']])->assertUnprocessable();
        $other=DB::table('communication_companies')->insertGetId(['name'=>'Other approval company','created_at'=>now(),'updated_at'=>now()]);
        $u=$this->member('manager');UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$other],'accounting_role'=>'manager']);$this->asUser($u);
        $this->getJson('/accounting/api/approvals?company_id='.$this->company)->assertForbidden();
        $this->decision($a)->assertForbidden();
    }

    private function finish(object $a): void
    {
        if($a->workflow_mode==='paper'){$this->attach($a)->assertOk();$this->decision($this->latest($a),'post')->assertOk();return;}
        foreach (['approve','approve','approve','post'] as $action) {
            $this->decision($this->latest($a),$action)->assertOk();
        }
    }

    public function test_invoice_and_payment_approvals_have_no_ledger_effect_until_final_posting(): void
    {
        $party=DB::table('accounting_parties')->insertGetId(['company_id'=>$this->company,'kind'=>'customer','name'=>'Approval customer','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $i=$this->postJson('/accounting/api/invoices',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'kind'=>'sale','party_id'=>$party,'control_account_id'=>$this->accounts['01-01-03'],'posting_date'=>now()->toDateString(),'due_date'=>now()->addDays(10)->toDateString(),'tax_percent'=>'0.00','lines'=>[['description'=>'Service','account_id'=>$this->accounts['04-01'],'quantity'=>'1.00','rate'=>'100.00']]])->assertOk()->json('invoice');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertUnprocessable();
        $this->postJson('/accounting/api/approvals/submit',['company_id'=>$this->company,'type'=>'invoice','id'=>$i['id'],'version'=>1,'note'=>'Please approve this invoice.'])->assertOk();
        $a=DB::table('accounting_approvals')->where('document_type','invoice')->where('document_id',$i['id'])->first();
        $this->assertSame(0,DB::table('accounting_journals')->where('company_id',$this->company)->where('status','posted')->count());
        $this->finish($a);
        $this->assertSame('posted',DB::table('accounting_invoices')->find($i['id'])->status);
        $payload=['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'bank_account_id'=>$this->accounts['01-01-02'],'reference'=>'Customer transfer','amount'=>'80.00'];
        $url='/accounting/api/invoices/'.$i['id'].'/payments';
        $this->postJson($url,$payload)->assertOk()->assertJsonPath('invoice.outstanding','100.00')->assertJsonPath('invoice.paid','0.00');
        $this->postJson($url,$payload)->assertOk();
        $this->assertSame(1,DB::table('accounting_payment_requests')->where('company_id',$this->company)->count());
        $this->assertSame(0,DB::table('accounting_payments')->where('company_id',$this->company)->count());
        $this->postJson($url,array_replace($payload,['request_key'=>(string)Str::uuid(),'amount'=>'21.00']))->assertUnprocessable();
        $a=DB::table('accounting_approvals')->where('company_id',$this->company)->where('document_type','payment')->first();
        $this->finish($a);
        $this->getJson('/accounting/api/invoices?company_id='.$this->company.'&id='.$i['id'])->assertOk()->assertJsonPath('invoice.outstanding','20.00')->assertJsonPath('invoice.paid','80.00');
        $this->assertSame(1,DB::table('accounting_payments')->where('company_id',$this->company)->count());
    }

    public function test_pdc_clearance_waits_for_payment_approval_and_updates_register_after_posting(): void
    {
        $party=DB::table('accounting_parties')->insertGetId(['company_id'=>$this->company,'kind'=>'customer','name'=>'Approval customer','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $i=$this->postJson('/accounting/api/invoices',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'kind'=>'sale','party_id'=>$party,'control_account_id'=>$this->accounts['01-01-03'],'posting_date'=>now()->toDateString(),'due_date'=>now()->addDays(10)->toDateString(),'tax_percent'=>'0.00','lines'=>[['description'=>'Service','account_id'=>$this->accounts['04-01'],'quantity'=>'1.00','rate'=>'100.00']]])->assertOk()->json('invoice');
        $this->postJson('/accounting/api/invoices/'.$i['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertUnprocessable();
        $this->postJson('/accounting/api/approvals/submit',['company_id'=>$this->company,'type'=>'invoice','id'=>$i['id'],'version'=>1,'note'=>'Please approve this invoice.'])->assertOk();
        $a=DB::table('accounting_approvals')->where('document_type','invoice')->where('document_id',$i['id'])->first();
        $this->assertSame(0,DB::table('accounting_journals')->where('company_id',$this->company)->where('status','posted')->count());
        $this->finish($a);
        $this->assertSame('posted',DB::table('accounting_invoices')->find($i['id'])->status);
        $this->postJson('/accounting/api/cheques',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'invoice_id'=>$i['id'],'bank_account_id'=>$this->accounts['01-01-02'],'cheque_number'=>'PDC-APPROVAL','drawee_bank'=>'MCB','recorded_date'=>now()->toDateString(),'cheque_date'=>now()->toDateString(),'amount'=>'80.00'])->assertOk();
        $c=DB::table('accounting_cheques')->where('invoice_id',$i['id'])->first();
        $this->postJson('/accounting/api/cheques/'.$c->id.'/actions',['company_id'=>$this->company,'version'=>1,'action'=>'clear','date'=>now()->toDateString(),'note'=>'Actual bank clearance confirmed'])->assertOk();
        $this->assertSame('clearing',DB::table('accounting_cheques')->find($c->id)->status);
        $this->assertSame(0,DB::table('accounting_payments')->where('company_id',$this->company)->count());
        $approval=DB::table('accounting_approvals')->where('company_id',$this->company)->where('document_type','payment')->first();
        $this->finish($approval);
        $this->assertSame('cleared',DB::table('accounting_cheques')->find($c->id)->status);
        $this->assertSame(1,DB::table('accounting_payments')->where('company_id',$this->company)->count());
    }

    public function test_closing_a_period_while_approval_is_pending_blocks_final_posting(): void
    {
        $j=$this->draft();$a=$this->submit($j);
        $this->attach($a)->assertOk();
        DB::table('accounting_books')->where('company_id',$this->company)->update(['locked_through'=>now()->toDateString()]);
        $this->decision($this->latest($a),'post')->assertUnprocessable();
        $this->assertSame('manager_review',$this->latest($a)->stage);
        $this->assertSame('draft',DB::table('accounting_journals')->find($j['id'])->status);
    }

    public function test_custom_accounting_role_is_available_in_administration_and_invitation_acceptance(): void
    {
        $role=$this->postJson('/accounting/api/roles',['name'=>'Treasury reviewer','active'=>true,'permissions'=>['cash_receipts.view','cash_receipts.print']])->assertOk()->json('role');
        $this->getJson('/admin/api')->assertOk()->assertJsonFragment(['slug'=>$role['slug'],'name'=>'Treasury reviewer']);
        $this->postJson('/admin/api/users',['name'=>'Treasury employee','email'=>'treasury-assignment@example.test','password'=>'TestPassword123!','is_active'=>true,'product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>$role['slug']])->assertOk();
        $u=User::where('email','treasury-assignment@example.test')->firstOrFail();
        $this->assertDatabaseHas('accounting_members',['user_id'=>$u->id,'role'=>$role['slug']]);
        $response=$this->postJson('/admin/api/invitations',['name'=>'Invited treasury','email'=>'treasury-invitation@example.test','delivery_method'=>'manual','product_slugs'=>['accounts'],'company_ids'=>[$this->company],'accounting_role'=>$role['slug']])->assertCreated();
        $token=basename($response->json('invitation_url'));
        $this->withSession(['judibas_admin'=>false])->postJson('/invitations/'.$token,['name'=>'Invited treasury','password'=>'TestPassword123!','password_confirmation'=>'TestPassword123!'])->assertOk();
        $invited=User::where('email','treasury-invitation@example.test')->firstOrFail();
        $this->assertDatabaseHas('accounting_members',['user_id'=>$invited->id,'role'=>$role['slug']]);
        $this->actingAs($invited)->getJson('/accounting/api/data')->assertOk()->assertJsonPath('permissions',['cash_receipts.view','cash_receipts.print']);
    }

    public function test_latest_draft_editor_cannot_approve_their_own_changes(): void
    {
        $maker=$this->member('finance_officer');$editor=$this->member('senior_finance_manager');
        $this->asUser($maker);$j=$this->draft();
        $this->asUser($editor);
        $j=$this->postJson('/accounting/api/journals',array_merge($j,['remarks'=>'Prepared by second officer']))->assertOk()->json('journal');
        $a=$this->submit($j);
        $this->assertSame($editor->id,$a->prepared_by);
        $this->attach($a)->assertOk();$a=$this->latest($a);
        $this->decision($a,'post')->assertForbidden();
        $this->asUser($this->member('senior_finance_manager'));$this->decision($a,'post')->assertOk();
    }

    public function test_demo_credentials_are_company_scoped_and_seeder_does_not_duplicate_entries(): void
    {
        // Isolate fixed demo identities inside this test transaction; rollback restores local accounts.
        foreach(User::whereIn('email',array_keys(\Database\Seeders\AccountingApprovalDemoSeeder::USERS))->get() as $existing){
            DB::table('users')->where('id',$existing->id)->update(['email'=>'isolated-'.$existing->id.'-'.Str::uuid().'@example.test']);
        }
        $existingDemo=DB::table('communication_companies')->where('name',\Database\Seeders\AccountingApprovalDemoSeeder::COMPANY)->first();
        if($existingDemo)DB::table('communication_companies')->where('id',$existingDemo->id)->update(['name'=>'Isolated demo '.$existingDemo->id.' '.Str::uuid()]);
        $seeder=app(\Database\Seeders\AccountingApprovalDemoSeeder::class);
        $seeder->run();
        $company=(int)DB::table('communication_companies')->where('name',\Database\Seeders\AccountingApprovalDemoSeeder::COMPANY)->value('id');
        $before=DB::table('accounting_journals')->where('company_id',$company)->count();
        $seeder->run();
        $this->assertSame($before,DB::table('accounting_journals')->where('company_id',$company)->count());
        foreach (\Database\Seeders\AccountingApprovalDemoSeeder::USERS as $email=>[$name,$role]) {
            $user=User::where('email',$email)->firstOrFail();
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password',$user->password));
            $this->assertFalse($user->judibas_administrator);
            $this->assertDatabaseHas('accounting_members',['user_id'=>$user->id,'role'=>$role]);
        }
        $this->asUser(User::where('email','ceo@atlasaccounts.com')->firstOrFail());
        $this->getJson('/accounting/api/data')->assertOk()->assertJsonCount(1,'companies')->assertJsonPath('company_id',$company);
        $this->getJson('/accounting/api/approvals?company_id='.$company)->assertOk();
        $this->getJson('/accounting/api/data?company_id='.$this->company)->assertForbidden();
        $this->getJson('/accounting/api/approvals?company_id='.$this->company)->assertForbidden();
        $this->getJson('/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'report'=>'profit','from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()]))->assertForbidden();
    }

    public function test_multi_line_bank_voucher_completes_approval_and_cannot_bypass_it(): void
    {
        $this->asUser($this->member('finance_officer'));
        $j=$this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'bank_receipt','voucher_person'=>'Multiple customer receipts','lines'=>[
            ['account_id'=>$this->accounts['01-01-02'],'debit'=>'150.03','credit'=>'0.00'],
            ['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>'100.01','description'=>'Customer one'],
            ['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>'50.02','description'=>'Customer two'],
        ]])->assertOk()->json('journal');
        $a=$this->submit($j);
        $this->withSession(['judibas_admin'=>true]);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertUnprocessable();
        $this->finish($a);
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&id='.$j['id'])->assertOk()->assertJsonPath('journal.status','posted')->assertJsonPath('journal.total','150.03')->assertJsonCount(3,'journal.lines');
    }

    public function test_single_voucher_list_and_new_actions_respect_individual_type_permissions(): void
    {
        $j=$this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'bank_receipt','voucher_person'=>'Bank receipt','lines'=>[['account_id'=>$this->accounts['01-01-02'],'debit'=>'10.00','credit'=>'0.00'],['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>'10.00']]])->assertOk()->json('journal');
        $role=$this->postJson('/accounting/api/roles',['name'=>'Cash receipts only','active'=>true,'permissions'=>['cash_receipts.view']])->assertOk()->json('role');
        $this->asUser($this->member($role['slug']));
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&vouchers=1')->assertOk()->assertJsonPath('total',0);
        $this->getJson('/accounting/api/journals?company_id='.$this->company.'&id='.$j['id'])->assertForbidden();
        $this->get('/accounting/vouchers/'.$j['id'].'/print?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertForbidden();
    }

    public function test_signed_copy_upload_from_draft_and_private_company_scoped_preview(): void
    {
        $maker=$this->member('finance_officer');$this->asUser($maker);$j=$this->draft();
        $this->post('/accounting/api/approvals/0/signed-copy',['company_id'=>$this->company,'initial'=>1,'document_id'=>$j['id'],'version'=>$j['version'],'note'=>'CEO signature on the printed voucher.','signed_copy'=>\Illuminate\Http\UploadedFile::fake()->image('signed-voucher.jpg')],['Accept'=>'application/json'])->assertOk();
        $a=DB::table('accounting_approvals')->where('document_type','journal')->where('document_id',$j['id'])->first();
        $copy=\App\Modules\Accounting\Services\ApprovalFlow::signedCopy($a);
        $this->assertNotNull($copy);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($copy->path);
        $this->assertSame($maker->id,$copy->uploaded_by);
        $this->get('/accounting/api/signed-copies/'.$copy->id.'?company_id='.$this->company)->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->asUser($this->member('ceo'));
        $this->get('/accounting/api/signed-copies/'.$copy->id.'?company_id='.$this->company)->assertOk();
        $this->attach($a)->assertForbidden();
        $other=$this->member('manager');
        $company=DB::table('communication_companies')->insertGetId(['name'=>'Unrelated signed-copy company','created_at'=>now(),'updated_at'=>now()]);
        UserProductAccess::sync($other,['product_slugs'=>['accounts'],'company_ids'=>[$company],'accounting_role'=>'manager']);$this->asUser($other);
        $this->get('/accounting/api/signed-copies/'.$copy->id.'?company_id='.$this->company)->assertForbidden();
        $this->get('/accounting/api/signed-copies/'.$copy->id.'?company_id='.$company)->assertNotFound();
    }

    public function test_invalid_or_stale_signed_upload_leaves_no_files_or_records(): void
    {
        $this->asUser($this->member('finance_officer'));$a=$this->submit($this->draft());
        $url='/accounting/api/approvals/'.$a->id.'/signed-copy';
        $base=['company_id'=>$this->company,'version'=>$a->version,'note'=>'Signed paper voucher copy.'];
        $this->post($url,$base+['signed_copy'=>\Illuminate\Http\UploadedFile::fake()->create('fake.jpg',10,'text/plain')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->post($url,$base+['signed_copy'=>\Illuminate\Http\UploadedFile::fake()->create('large.pdf',11000,'application/pdf')],['Accept'=>'application/json'])->assertUnprocessable();
        $this->post($url,array_replace($base,['version'=>99,'signed_copy'=>\Illuminate\Http\UploadedFile::fake()->create('signed.pdf',10,'application/pdf')]),['Accept'=>'application/json'])->assertConflict();
        $this->assertSame(0,DB::table('accounting_signed_copies')->where('approval_id',$a->id)->count());
        $this->assertSame([],\Illuminate\Support\Facades\Storage::disk('local')->allFiles());
    }

    public function test_return_and_resubmit_require_fresh_signed_evidence_even_without_amount_changes(): void
    {
        $maker=$this->member('finance_officer');$head=$this->member('senior_finance_manager');$this->asUser($maker);$j=$this->draft();$a=$this->submit($j);$this->attach($a)->assertOk();
        $a=$this->latest($a);$oldCycle=$a->submission_version;$this->asUser($head);$this->decision($a,'return')->assertOk();
        $this->asUser($maker);$a=$this->submit($j);
        $this->assertSame($oldCycle+1,$a->submission_version);
        $this->assertNull(\App\Modules\Accounting\Services\ApprovalFlow::signedCopy($a));
        $this->getJson('/accounting/api/approvals/document?'.http_build_query(['company_id'=>$this->company,'type'=>'journal','id'=>$j['id']]))->assertOk()->assertJsonPath('approval.signed_copies.0.current',false);
        $this->attach($a)->assertOk();
        $this->assertSame(2,DB::table('accounting_signed_copies')->where('approval_id',$a->id)->count());
        $this->asUser($head);$this->decision($this->latest($a),'post')->assertOk();
    }

    public function test_the_signed_copy_uploader_cannot_be_the_final_verifier_and_missing_file_blocks_posting(): void
    {
        $maker=$this->member('finance_officer');$uploader=$this->member('senior_finance_manager');$head=$this->member('senior_finance_manager');
        $this->asUser($maker);$a=$this->submit($this->draft());$this->asUser($uploader);$this->attach($a)->assertOk();$a=$this->latest($a);
        $this->decision($a,'post')->assertForbidden();
        $copy=\App\Modules\Accounting\Services\ApprovalFlow::signedCopy($a);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($copy->path);
        $this->asUser($head);$this->decision($a,'post')->assertUnprocessable();
        $this->assertSame('manager_review',$this->latest($a)->stage);
    }

    public function test_returned_voucher_can_upload_a_new_copy_directly_without_reusing_old_evidence(): void
    {
        $maker=$this->member('finance_officer');$head=$this->member('senior_finance_manager');$this->asUser($maker);$j=$this->draft();$a=$this->submit($j);$this->attach($a)->assertOk();
        $a=$this->latest($a);$this->asUser($head);$this->decision($a,'return')->assertOk();
        $this->asUser($maker);
        $this->post('/accounting/api/approvals/0/signed-copy',['company_id'=>$this->company,'initial'=>1,'document_id'=>$j['id'],'version'=>$j['version'],'note'=>'CEO signed paper resubmitted after correction request.','signed_copy'=>\Illuminate\Http\UploadedFile::fake()->create('new-signed.pdf',10,'application/pdf')],['Accept'=>'application/json'])->assertOk();
        $current=$this->latest($a);
        $this->assertSame('manager_review',$current->stage);
        $this->assertSame($a->submission_version+1,$current->submission_version);
        $this->assertSame(2,DB::table('accounting_signed_copies')->where('approval_id',$a->id)->count());
        $this->assertSame('new-signed.pdf',\App\Modules\Accounting\Services\ApprovalFlow::signedCopy($current)->original_name);
        $this->asUser($head);$this->decision($current,'post')->assertOk();
    }

    private function scope(array $types)
    {
        $book=DB::table('accounting_books')->where('company_id',$this->company)->first();
        return $this->postJson('/accounting/api/books',['company_id'=>$this->company,'version'=>$book->version,'currency'=>$book->currency,'start_date'=>$book->start_date,'approval_required'=>true,'approval_types'=>$types]);
    }

    private function receiptDraft(): array
    {
        return $this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'cash_receipt','voucher_person'=>'Policy test receipt','lines'=>[['account_id'=>$this->accounts['01-01-01'],'debit'=>'100.00','credit'=>'0.00'],['account_id'=>$this->accounts['04-01'],'debit'=>'0.00','credit'=>'100.00']]])->assertOk()->json('journal');
    }

    public function test_only_selected_document_types_require_approval_but_unselected_drafts_still_need_posting(): void
    {
        $this->scope(['cash_receipt'])->assertOk();
        $this->getJson('/accounting/api/data?company_id='.$this->company)->assertOk()->assertJsonPath('book.approval_types',['cash_receipt']);
        $j=$this->draft();
        $this->getJson('/accounting/api/approvals/document?'.http_build_query(['company_id'=>$this->company,'type'=>'journal','id'=>$j['id']]))->assertOk()->assertJsonPath('enabled',false)->assertJsonPath('can_submit',false);
        $this->postJson('/accounting/api/approvals/submit',['company_id'=>$this->company,'type'=>'journal','id'=>$j['id'],'version'=>1,'note'=>'Should not start approval for an unticked type.'])->assertUnprocessable();
        $this->assertSame('draft',DB::table('accounting_journals')->find($j['id'])->status);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertOk();
        $receipt=$this->receiptDraft();
        $this->postJson('/accounting/api/journals/'.$receipt['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertUnprocessable();
        $a=$this->submit($receipt);$this->finish($a);
        $this->assertSame('posted',DB::table('accounting_journals')->find($receipt['id'])->status);
    }

    public function test_scope_changes_keep_pending_workflow_and_require_super_admin(): void
    {
        $this->scope(['general','cash_receipt'])->assertOk();$a=$this->submit($this->receiptDraft());
        $this->scope(['cash_receipt'])->assertOk();
        $this->scope([])->assertOk();
        $this->decision($a,'withdraw')->assertOk();
        $this->scope([])->assertOk();
        // A withdrawn approval cannot force an unticked document back into the workflow.
        $this->postJson('/accounting/api/journals/'.$a->document_id.'/post',['company_id'=>$this->company,'version'=>1])->assertOk();
        $this->asUser($this->member('senior_finance_manager'));
        $this->scope(['cash_receipt'])->assertForbidden();
    }

    private function assertNoFinancialEffect(): void
    {
        $base=['company_id'=>$this->company,'from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString()];
        $this->getJson('/accounting/api/reports?'.http_build_query($base+['report'=>'ledger']))->assertOk()->assertJsonPath('entries.total',0)->assertJsonPath('closing','0.00');
        $this->getJson('/accounting/api/reports?'.http_build_query($base+['report'=>'trial']))->assertOk()->assertJsonPath('summary.debit','0.00')->assertJsonPath('summary.credit','0.00');
        foreach(['cash','accrual'] as $basis)$this->getJson('/accounting/api/reports?'.http_build_query($base+['report'=>'profit','basis'=>$basis]))->assertOk()->assertJsonPath('summary.income','0.00')->assertJsonPath('summary.expense','0.00')->assertJsonPath('summary.profit','0.00');
        $this->getJson('/accounting/api/reports?'.http_build_query($base+['report'=>'balance']))->assertOk()->assertJsonPath('summary.assets','0.00')->assertJsonPath('summary.accumulated_earnings','0.00');
    }

    public function test_non_posted_entries_are_excluded_from_reports_before_and_after_signed_copy_upload(): void
    {
        $this->scope(['cash_receipt'])->assertOk();$this->draft();$receipt=$this->receiptDraft();
        $this->assertNoFinancialEffect();$a=$this->submit($receipt);$this->assertNoFinancialEffect();
        $this->attach($a)->assertOk();$this->assertNoFinancialEffect();
        $this->decision($this->latest($a),'post')->assertOk();
        $this->getJson('/accounting/api/reports?'.http_build_query(['company_id'=>$this->company,'from'=>now()->startOfMonth()->toDateString(),'to'=>now()->toDateString(),'report'=>'profit']))->assertOk()->assertJsonPath('summary.income','100.00');
    }

    public function test_invoice_and_payment_approval_scopes_are_independent(): void
    {
        $this->scope(['customer_payment'])->assertOk();
        $party=DB::table('accounting_parties')->insertGetId(['company_id'=>$this->company,'kind'=>'customer','name'=>'Scope customer','active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $invoice=$this->postJson('/accounting/api/invoices',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'kind'=>'sale','party_id'=>$party,'control_account_id'=>$this->accounts['01-01-03'],'posting_date'=>now()->toDateString(),'due_date'=>now()->toDateString(),'tax_percent'=>'0.00','lines'=>[['description'=>'Scope test service','account_id'=>$this->accounts['04-01'],'quantity'=>'1.00','rate'=>'100.00']]])->assertOk()->json('invoice');
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/post',['company_id'=>$this->company,'version'=>1])->assertOk();
        $payment=['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'bank_account_id'=>$this->accounts['01-01-02'],'reference'=>'Scope transfer','amount'=>'60.00'];
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments',$payment)->assertOk()->assertJsonPath('invoice.paid','0.00');
        $a=DB::table('accounting_approvals')->where('company_id',$this->company)->where('document_type','payment')->first();
        $this->assertSame('finance_review',$a->stage);$this->scope([])->assertOk();$this->finish($a);
        $this->scope([])->assertOk();
        $this->postJson('/accounting/api/invoices/'.$invoice['id'].'/payments',array_replace($payment,['request_key'=>(string)Str::uuid(),'amount'=>'40.00']))->assertOk()->assertJsonPath('invoice.paid','100.00')->assertJsonPath('invoice.outstanding','0.00');
    }
}
