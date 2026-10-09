<?php

namespace Tests\Feature;

use App\Mail\CommunicationInvitation;
use App\Models\User;
use App\Services\CommunicationAccess as Access;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        config(['mail.default' => 'smtp']);
        Storage::fake('local');
    }

    private function company(string $name): int
    {
        $id = DB::table('communication_companies')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        Access::community($id);

        return $id;
    }

    private function member(int $company, bool $admin = false): User
    {
        $u = User::factory()->create(['is_active' => true, 'company_id' => $company, 'communication_admin' => $admin]);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);

        return $u;
    }

    private function super(): static
    {
        $this->withSession(['judibas_admin' => true]);

        return $this;
    }

    public function test_members_see_only_their_company_and_cannot_contact_other_companies(): void
    {
        $sky = $this->company('Test Sky');
        $crux = $this->company('Test Crux');
        $a = $this->member($sky);
        $b = $this->member($crux);
        $this->actingAs($a)->getJson('/communication/api')->assertJsonCount(1, 'companies')->assertJsonPath('companies.0.id', $sky)->assertJsonMissing(['id' => $b->id, 'email' => $b->email]);
        $this->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$b->id]])->assertForbidden();
        $this->getJson('/communication/api/conversations/'.Access::community($crux).'/messages')->assertForbidden();
        $this->super()->postJson('/communication/api/conversations', ['kind' => 'group', 'company_id' => $sky, 'name' => 'Shared group', 'member_ids' => [$a->id, $b->id]])->assertOk();
        $this->withSession(['judibas_admin' => false])->actingAs($a)->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$b->id]])->assertOk();
    }

    public function test_community_announcements_alert_only_selected_companies_and_members_can_react(): void
    {
        $sky = $this->company('Announcement Sky');
        $crux = $this->company('Announcement Crux');
        $a = $this->member($sky);
        $b = $this->member($crux);
        $id = $this->super()->post('/communication/api/announcements', ['company_ids' => [$sky], 'body' => 'Company news', 'attachment' => UploadedFile::fake()->createWithContent('brief.txt', 'Announcement file')], ['Accept' => 'application/json'])->assertCreated()->json('ids.0');
        $cid = Access::community($sky);
        $data = $this->withSession(['judibas_admin' => false])->actingAs($a)->getJson('/communication/api')->assertOk()->json();
        $this->assertEquals(1, collect($data['conversations'])->firstWhere('id', $cid)['unread']);
        $this->postJson("/communication/api/conversations/$cid/messages", ['body' => 'Not allowed'])->assertForbidden();
        $this->postJson("/communication/api/messages/$id/reactions", ['emoji' => '👍'])->assertOk();
        $this->getJson("/communication/api/conversations/$cid/messages")->assertJsonPath('messages.0.reactions.0.emoji', '👍');
        $this->deleteJson("/communication/api/messages/$id")->assertForbidden();
        $this->actingAs($b)->get("/communication/api/attachments/$id")->assertForbidden();
        $this->postJson("/communication/api/messages/$id/reactions", ['emoji' => '❤️'])->assertForbidden();
        $data = $this->getJson('/communication/api')->json();
        $this->assertEquals(0, collect($data['conversations'])->sum('unread'));
    }

    public function test_broadcast_attachment_remains_available_when_one_copy_is_deleted(): void
    {
        $a = $this->company('Broadcast A');
        $b = $this->company('Broadcast B');
        $ids = $this->super()->post('/communication/api/announcements', ['company_ids' => [$a, $b], 'attachment' => UploadedFile::fake()->createWithContent('brief.txt', 'Shared announcement')], ['Accept' => 'application/json'])->assertCreated()->json('ids');
        $this->deleteJson('/communication/api/messages/'.$ids[0])->assertOk();
        $this->get('/communication/api/attachments/'.$ids[1])->assertOk();
    }

    public function test_delegated_admin_can_invite_only_into_their_company_and_cannot_escalate(): void
    {
        $sky = $this->company('Delegate Sky');
        $crux = $this->company('Delegate Crux');
        $manager = $this->member($sky, true);
        $this->actingAs($manager)->getJson('/communication/api/management')->assertOk()->assertJsonCount(1, 'companies');
        $this->postJson('/communication/api/invitations', ['email' => 'invite-delegate@example.test', 'company_id' => $crux, 'group_ids' => []])->assertForbidden();
        $this->postJson('/communication/api/invitations', ['email' => 'invite-delegate@example.test', 'company_id' => $sky, 'group_ids' => [], 'communication_admin' => true])->assertForbidden();
        $this->postJson('/communication/api/invitations', ['email' => 'invite-delegate@example.test', 'company_id' => $sky, 'group_ids' => []])->assertCreated()->assertJsonPath('delivery_status', 'sent');
        Mail::assertSent(CommunicationInvitation::class, fn ($m) => $m->hasTo('invite-delegate@example.test'));
        $this->postJson('/communication/api/announcements', ['company_ids' => [$sky], 'body' => 'Unauthorized'])->assertForbidden();
        $this->getJson('/admin/api')->assertForbidden();
        $this->getJson('/communication/api/audits')->assertForbidden();
    }

    public function test_invitation_acceptance_sets_company_password_access_and_groups_once(): void
    {
        $company = $this->company('Invited Company');
        $member = $this->member($company);
        $gid = $this->super()->postJson('/communication/api/conversations', ['kind' => 'group', 'company_id' => $company, 'name' => 'Welcome group', 'member_ids' => [$member->id]])->json('id');
        $link = $this->postJson('/communication/api/invitations', ['email' => 'new-member@example.test', 'company_id' => $company, 'group_ids' => [$gid], 'communication_admin' => true])->assertCreated()->json('acceptance_url');
        $path = parse_url($link, PHP_URL_PATH);
        $this->assertDatabaseMissing('communication_invitations', ['token_hash' => basename($path)]);
        $this->withSession(['judibas_admin' => false])->get($path)->assertOk()->assertViewHas('portal', fn ($p) => $p['invitation']['email'] === 'new-member@example.test');
        $this->post($path, ['name' => 'New member', 'password' => 'chosen-password-123', 'password_confirmation' => 'chosen-password-123'])->assertRedirect('/communication');
        $u = User::where('email', 'new-member@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($u);
        $this->assertEquals($company, $u->company_id);
        $this->assertTrue($u->communication_admin);
        $this->assertDatabaseHas('communication_members', ['conversation_id' => $gid, 'user_id' => $u->id]);
        $this->post($path, ['name' => 'Replay', 'password' => 'another-password-123', 'password_confirmation' => 'another-password-123'])->assertStatus(410);
        $this->post('/logout');
        $this->post('/login', ['email' => $u->email, 'password' => 'chosen-password-123'])->assertRedirect('/communication');
    }

    public function test_existing_account_password_cannot_be_reset_through_an_invitation(): void
    {
        $company = $this->company('Existing Account');
        $u = $this->member($company);
        $path = parse_url($this->super()->postJson('/communication/api/invitations', ['email' => $u->email, 'company_id' => $company, 'group_ids' => []])->json('acceptance_url'), PHP_URL_PATH);
        $this->withSession(['judibas_admin' => false])->post($path, ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->post($path, ['password' => 'password'])->assertRedirect('/communication');
    }

    public function test_revoked_expired_and_permission_revoked_invitations_cannot_be_accepted(): void
    {
        $company = $this->company('Invitation Revocation');
        $manager = $this->member($company, true);
        $invite = $this->actingAs($manager)->postJson('/communication/api/invitations', ['email' => 'revoked-invite@example.test', 'company_id' => $company, 'group_ids' => []])->assertCreated()->json();
        $this->postJson('/communication/api/invitations/'.$invite['id'].'/revoke')->assertOk();
        $this->get(parse_url($invite['acceptance_url'], PHP_URL_PATH))->assertStatus(410);
        $next = $this->postJson('/communication/api/invitations', ['email' => 'expired-invite@example.test', 'company_id' => $company, 'group_ids' => []])->json();
        DB::table('communication_invitations')->where('id', $next['id'])->update(['expires_at' => now()->subDay()]);
        $this->get(parse_url($next['acceptance_url'], PHP_URL_PATH))->assertStatus(410);
        $last = $this->postJson('/communication/api/invitations', ['email' => 'lost-permission@example.test', 'company_id' => $company, 'group_ids' => []])->json();
        $manager->communication_admin = false;
        $manager->save();
        $this->get(parse_url($last['acceptance_url'], PHP_URL_PATH))->assertStatus(410);
    }

    public function test_company_is_required_and_only_super_can_assign_admin_roles(): void
    {
        $company = $this->company('Employee Setup');
        $v = ['name' => 'New staff', 'email' => 'required-company@example.test', 'password' => 'valid-password-123', 'is_active' => true, 'product_slugs' => ['communication'], 'communication_admin' => true];
        $this->super()->postJson('/admin/api/users', $v)->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $v['company_id'] = $company;
        $this->postJson('/admin/api/users', $v)->assertOk();
        $u = User::where('email', $v['email'])->firstOrFail();
        $this->assertTrue($u->communication_admin);
        $this->withSession(['judibas_admin' => false])->actingAs($u)->postJson('/admin/api/users',$v)->assertForbidden();
    }

    public function test_invitation_joins_multiple_companies_with_one_account(): void
    {
        $one=$this->company('Multi One');$two=$this->company('Multi Two');$outside=$this->company('Multi Outside');
        $peer=$this->member($two);$stranger=$this->member($outside);
        $response=$this->super()->postJson('/communication/api/invitations',['email'=>'multi@example.test','company_ids'=>[$one,$two],'group_ids'=>[]])->assertCreated();
        $path=parse_url($response->json('acceptance_url'),PHP_URL_PATH);
        $this->withSession(['judibas_admin'=>false])->post($path,['name'=>'Multi Member','password'=>'multi-company-password','password_confirmation'=>'multi-company-password'])->assertRedirect('/communication');
        $user=User::where('email','multi@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing([$one,$two],Access::companies($user->id));
        $this->assertDatabaseHas('communication_company_memberships',['user_id'=>$user->id,'company_id'=>$two]);
        $this->actingAs($user)->getJson('/communication/api')->assertJsonCount(2,'companies');
        $this->getJson('/communication/api/conversations/'.Access::community($two).'/messages')->assertOk();
        $this->getJson('/communication/api/conversations/'.Access::community($outside).'/messages')->assertForbidden();
        $this->postJson('/communication/api/conversations',['kind'=>'direct','member_ids'=>[$peer->id]])->assertOk();
        $this->postJson('/communication/api/conversations',['kind'=>'direct','member_ids'=>[$stranger->id]])->assertForbidden();
        Mail::assertSent(CommunicationInvitation::class);
    }
    public function test_manager_cannot_invite_outside_assigned_companies(): void
    {
        $one=$this->company('Manager Multi One');$two=$this->company('Manager Multi Two');$outside=$this->company('Manager Outside');
        $manager=$this->member($one,true);
        DB::table('communication_company_memberships')->insert(['user_id'=>$manager->id,'company_id'=>$two]);
        $this->actingAs($manager)->postJson('/communication/api/invitations',['email'=>'allowed@example.test','company_ids'=>[$one,$two],'group_ids'=>[]])->assertCreated();
        $this->postJson('/communication/api/invitations',['email'=>'denied@example.test','company_ids'=>[$one,$outside],'group_ids'=>[]])->assertForbidden();
        $this->postJson('/communication/api/invitations',['email'=>'empty@example.test','company_ids'=>[],'group_ids'=>[]])->assertUnprocessable();
        $this->postJson('/communication/api/invitations',['email'=>'duplicate@example.test','company_ids'=>[$one,$one],'group_ids'=>[]])->assertUnprocessable();
    }
    public function test_existing_user_accepts_extra_company_without_duplicate_account(): void
    {
        $one=$this->company('Existing One');$two=$this->company('Existing Two');
        $user=$this->member($one);$user->password='existing-user-password';$user->save();
        $response=$this->super()->postJson('/communication/api/invitations',['email'=>$user->email,'company_ids'=>[$two],'group_ids'=>[]])->assertCreated();
        $path=parse_url($response->json('acceptance_url'),PHP_URL_PATH);
        $this->withSession(['judibas_admin'=>false])->postJson($path,['password'=>'incorrect'])->assertUnprocessable();
        $this->post($path,['password'=>'existing-user-password'])->assertRedirect('/communication');
        $this->assertEqualsCanonicalizing([$one,$two],Access::companies($user->id));
        $this->assertEquals(1,User::where('email',$user->email)->count());
    }

    public function test_super_admin_creates_and_updates_employee_company_memberships(): void
    {
        $one=$this->company('Employee One');$two=$this->company('Employee Two');
        $payload=['name'=>'Multi Employee','email'=>'employee-multi@example.test','password'=>'employee-test-password','is_active'=>true,'company_ids'=>[$one,$two],'product_slugs'=>['communication'],'communication_admin'=>false];
        $this->super()->postJson('/communication/api/users',$payload)->assertOk();
        $user=User::where('email',$payload['email'])->firstOrFail();
        $this->assertEqualsCanonicalizing([$one,$two],Access::companies($user->id));
        $this->getJson('/communication/api/users')->assertJsonFragment(['company_ids'=>[$one,$two]]);
        $payload['company_ids']=[$two];$payload['password']='';
        $this->postJson('/communication/api/users/'.$user->id,$payload)->assertOk();
        $this->assertSame([$two],Access::companies($user->id));
        $payload['company_ids']=[];
        $this->postJson('/communication/api/users/'.$user->id,$payload)->assertUnprocessable();
        $this->withSession(['judibas_admin'=>false])->actingAs($user)->postJson('/communication/api/users',$payload)->assertForbidden();
    }
}
