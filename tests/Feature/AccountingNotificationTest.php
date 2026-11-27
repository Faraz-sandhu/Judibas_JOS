<?php
namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Events\ActivityChanged;
use App\Modules\Accounting\Services\AccountingAccess;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Event,Broadcast};
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountingNotificationTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Concerns\UploadsAccountingProofFixtures;
    private int $company;
    protected function setUp():void
    {
        parent::setUp();Event::fake([ActivityChanged::class]);
        $this->company=DB::table('communication_companies')->insertGetId(['name'=>'Notification test company','created_at'=>now(),'updated_at'=>now()]);
        $this->withSession(['judibas_admin'=>true]);
        $this->postJson('/accounting/api/initialize',['company_id'=>$this->company])->assertOk();
        $this->postJson('/accounting/api/books',['company_id'=>$this->company,'version'=>0,'currency'=>'PKR','start_date'=>now()->startOfMonth()->toDateString()])->assertOk();
        Event::fake([ActivityChanged::class]);
    }
    private function member(string $role='finance_officer',?int $company=null):User
    {
        $u=User::factory()->create(['is_active'=>true]);UserProductAccess::sync($u,['product_slugs'=>['accounts'],'company_ids'=>[$company??$this->company],'accounting_role'=>$role]);return $u;
    }
    private function actor(User $u):void {$this->withSession(['judibas_admin'=>false])->actingAs($u);}
    private function draft():array
    {
        $accounts=DB::table('accounting_accounts')->where('company_id',$this->company)->pluck('id','code');
        return $this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'general','remarks'=>'Notification test','lines'=>[['account_id'=>$accounts['01-01-01'],'debit'=>'10.00','credit'=>'0.00'],['account_id'=>$accounts['04-01'],'debit'=>'0.00','credit'=>'10.00']]])->assertOk()->json('journal');
    }
    public function test_finance_activity_creates_persistent_admin_notification_and_private_event():void
    {
        $finance=$this->member();$this->actor($finance);$j=$this->draft();
        $this->getJson('/accounting/api/notifications')->assertForbidden();
        $this->withSession(['judibas_admin'=>true]);
        $this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->assertJsonPath('unread_count',1)->assertJsonPath('items.0.actor_name',$finance->name)->assertJsonPath('items.0.subject',$j['number'])->assertJsonPath('items.0.url',\App\Services\CompanyWorkspace::url('accounting',$this->company).'?entry_id='.$j['id'].'#journals');
        Event::assertDispatched(ActivityChanged::class,function($event){
            $this->assertSame(['company_id'=>$this->company,'notification_id'=>$event->notificationId],$event->broadcastWith());
            $this->assertSame('accounting.activity',$event->broadcastAs());
            $this->assertSame(['private-accounting.activity.admin','private-accounting.activity.company.'.$this->company,'private-accounting.workspace.admin','private-accounting.workspace.company.'.$this->company],array_map(fn($c)=>$c->name,$event->broadcastOn()));
            return true;
        });
    }
    public function test_reads_are_per_viewer_and_mark_all_does_not_read_newer_activity():void
    {
        $finance=$this->member();$manager=$this->member('manager');$this->actor($finance);$this->draft();
        $this->withSession(['judibas_admin'=>true]);$first=$this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->json();
        $this->actor($finance);$this->draft();$this->withSession(['judibas_admin'=>true]);
        $this->postJson('/accounting/api/notifications/read',['company_id'=>$this->company,'through_id'=>$first['latest_id']])->assertOk();
        $this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->assertJsonPath('unread_count',1);
        $this->actor($manager);$view=$this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->json();
        $this->assertGreaterThanOrEqual(2,$view['unread_count']);
        $this->postJson('/accounting/api/notifications/read',['company_id'=>$this->company,'id'=>$view['items'][0]['id']])->assertOk();
        $this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->assertJsonPath('items.0.read_at',DB::table('accounting_notification_reads')->where('notification_id',$view['items'][0]['id'])->where('viewer_key','user:'.$manager->id)->value('read_at'));
        $this->withSession(['judibas_admin'=>true]);$this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->assertJsonPath('unread_count',1);
    }
    public function test_company_scope_and_permission_revocation_are_enforced_for_feed_and_reads():void
    {
        $finance=$this->member();$this->actor($finance);$this->draft();
        $id=DB::table('accounting_activity_notifications')->where('company_id',$this->company)->max('id');
        $otherCompany=DB::table('communication_companies')->insertGetId(['name'=>'Other notification company','created_at'=>now(),'updated_at'=>now()]);
        $manager=$this->member('manager',$otherCompany);$this->actor($manager);
        $this->getJson('/accounting/api/notifications')->assertOk()->assertJsonPath('unread_count',0)->assertJsonCount(0,'items');
        $this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/notifications/read',['id'=>$id])->assertNotFound();
        DB::table('user_product_access')->where('user_id',$manager->id)->where('product_slug','accounts')->delete();
        $this->getJson('/accounting/api/notifications')->assertForbidden();
    }
    public function test_realtime_auth_works_for_session_super_admin_and_only_authorised_company_channels():void
    {
        config(['broadcasting.default'=>'reverb','broadcasting.connections.reverb'=>['driver'=>'reverb','key'=>'notification-test-key','secret'=>'notification-test-secret','app_id'=>'notification-test','options'=>['host'=>'127.0.0.1','port'=>8080,'scheme'=>'http','useTLS'=>false]]]);Broadcast::forgetDrivers();
        $this->getJson('/accounting/api/notifications/realtime/config')->assertOk()->assertJsonPath('channels',['accounting.activity.admin']);
        $auth=['socket_id'=>'123.456','channel_name'=>'private-accounting.activity.admin'];
        $this->postJson('/accounting/api/notifications/realtime/auth',$auth)->assertOk()->assertJsonStructure(['auth']);
        $manager=$this->member('manager');$this->actor($manager);
        $this->postJson('/accounting/api/notifications/realtime/auth',$auth)->assertForbidden();
        $this->postJson('/accounting/api/notifications/realtime/auth',array_replace($auth,['channel_name'=>'private-accounting.activity.company.'.$this->company]))->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/accounting/api/notifications/realtime/auth',array_replace($auth,['channel_name'=>'private-accounting.activity.company.999999']))->assertForbidden();
        $this->actor($this->member());$this->postJson('/accounting/api/notifications/realtime/auth',$auth)->assertForbidden();
    }
    public function test_rolled_back_actions_do_not_leave_notifications_or_broadcast():void
    {
        $user=$this->member();$r=Request::create('/test','POST');$r->setLaravelSession(app('session')->driver());$r->session()->put('judibas_admin',false);$r->setUserResolver(fn()=>$user);
        $before=DB::table('accounting_activity_notifications')->count();
        try{DB::transaction(function()use($r){AccountingAccess::audit($r,$this->company,'Test mutation','Rollback marker');Event::assertNotDispatched(ActivityChanged::class);throw new \RuntimeException('Roll back this test mutation.');});}catch(\RuntimeException $e){$this->assertSame('Roll back this test mutation.',$e->getMessage());}
        $this->assertSame($before,DB::table('accounting_activity_notifications')->count());Event::assertNotDispatched(ActivityChanged::class);
    }
    public function test_voucher_notification_opens_exact_record_and_respects_type_permission():void
    {
        $finance=$this->member();$this->actor($finance);
        $ids=DB::table('accounting_accounts')->where('company_id',$this->company)->pluck('id','code');
        $j=$this->postJson('/accounting/api/journals',['company_id'=>$this->company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>'cash_receipt','voucher_person'=>'Test customer','lines'=>[['account_id'=>$ids['01-01-01'],'debit'=>'10.00','credit'=>'0.00'],['account_id'=>$ids['04-01'],'debit'=>'0.00','credit'=>'10.00']]])->assertOk()->json('journal');
        $this->withSession(['judibas_admin'=>true]);
        $this->getJson('/accounting/api/notifications?company_id='.$this->company)->assertOk()->assertJsonPath('items.0.url',\App\Services\CompanyWorkspace::url('accounting',$this->company).'?entry_id='.$j['id'].'#single-vouchers');
    }

    public function test_workspace_updates_do_not_require_notification_permission_and_reject_other_companies():void
    {
        $finance=$this->member();$this->actor($finance);
        $this->getJson('/accounting/api/notifications')->assertForbidden();
        $this->getJson('/accounting/api/realtime/config')->assertOk()->assertJsonPath('channels',['accounting.workspace.company.'.$this->company]);
        $this->postJson('/accounting/api/realtime/auth',['socket_id'=>'1.2','channel_name'=>'private-accounting.workspace.company.'.($this->company+99999)])->assertForbidden();
        $this->postJson('/accounting/api/realtime/auth',['socket_id'=>'1.2','channel_name'=>'private-accounting.workspace.admin'])->assertForbidden();
        config(['broadcasting.default'=>'reverb']);
        Broadcast::shouldReceive('connection')->once()->andReturnSelf();
        Broadcast::shouldReceive('validAuthenticationResponse')->once()->andReturn(['auth'=>'authorized']);
        $this->postJson('/accounting/api/realtime/auth',['socket_id'=>'1.2','channel_name'=>'private-accounting.workspace.company.'.$this->company])->assertOk()->assertJsonPath('auth','authorized');
    }
    public function test_workspace_realtime_rejects_users_without_accounting_access():void
    {
        $this->actor(User::factory()->create(['is_active'=>true]));
        $this->getJson('/accounting/api/realtime/config')->assertForbidden();
    }
}
