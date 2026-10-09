<?php

namespace Tests\Feature;

use App\Events\CommunicationChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommunicationRealtimeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function employee(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        DB::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'communication']);

        return $user;
    }

    public function test_private_channels_require_active_communication_access_and_own_identity(): void
    {
        config(['broadcasting.default' => 'reverb']);
        $data = ['socket_id' => '123.456', 'channel_name' => 'private-communication.user.super'];
        $this->postJson('/broadcasting/auth', $data)->assertForbidden();
        $user = $this->employee();
        $this->actingAs($user)->postJson('/broadcasting/auth', $data)->assertForbidden();
        $data['channel_name'] = 'private-communication.user.'.$user->id;
        $this->postJson('/broadcasting/auth', $data)->assertOk()->assertJsonStructure(['auth']);
        $data['channel_name'] = 'private-communication.user.'.($user->id + 100000);
        $this->postJson('/broadcasting/auth', $data)->assertForbidden();
        $user->forceFill(['is_active' => false])->save();
        $data['channel_name'] = 'private-communication.user.'.$user->id;
        $this->postJson('/broadcasting/auth', $data)->assertForbidden();
    }

    public function test_presence_updates_do_not_broadcast_workspace_refreshes(): void
    {
        Event::fake([CommunicationChanged::class,\App\Events\CommunicationPresence::class]);
        $u=$this->employee();$this->actingAs($u)->postJson('/communication/api/presence',['tab'=>'10000000-1000-4000-8000-100000000099','activity'=>'online'])->assertOk();
        Event::assertNotDispatched(CommunicationChanged::class);
        Event::assertDispatched(\App\Events\CommunicationPresence::class);
    }
    public function test_batched_channel_auth_preserves_individual_authorization(): void
    {
        $u=$this->employee();$mine='private-communication.user.'.$u->id;$denied='private-communication.user.super';$presence='presence-communication.presence.'.$u->id;
        $r=$this->actingAs($u)->postJson('/broadcasting/auth/batch',['socket_id'=>'123.456','channels'=>[$mine,$denied,$presence]])->assertOk()->json('channels');
        $this->assertArrayHasKey('auth',$r[$mine]['data']);$this->assertArrayHasKey('auth',$r[$presence]['data']);$this->assertArrayHasKey('error',$r[$denied]);
        $u->forceFill(['is_active'=>false])->save();$this->postJson('/broadcasting/auth/batch',['socket_id'=>'123.456','channels'=>[$mine]])->assertForbidden();
    }

    public function test_super_admin_session_authenticates_without_an_employee_account(): void
    {
        config(['broadcasting.default' => 'reverb']);
        $this->withSession(['judibas_admin' => true])->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-communication.user.super'])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-communication.user.1'])->assertForbidden();
    }

    public function test_new_messages_are_delivered_only_to_active_participants_without_storage_paths(): void
    {
        Event::fake([CommunicationChanged::class]);
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $id = DB::table('communication_conversations')->insertGetId(['kind' => 'group', 'name' => 'Realtime group', 'company_id' => $a->company_id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('communication_members')->insert([['conversation_id' => $id, 'user_id' => $a->id], ['conversation_id' => $id, 'user_id' => $b->id]]);
        $this->actingAs($a)->postJson('/communication/api/conversations/'.$id.'/messages', ['body' => 'Private message'])->assertCreated();
        Event::assertDispatched(CommunicationChanged::class, fn ($e) => in_array($a->id, $e->recipients) && in_array($b->id, $e->recipients) && ! in_array($outsider->id, $e->recipients) && $e->broadcastWith()['message']['body'] === 'Private message' && ! array_key_exists('attachment_path', $e->broadcastWith()['message']) && $e->conversationId === $id);
        Event::fake([CommunicationChanged::class]);
        $this->actingAs($outsider)->postJson('/communication/api/conversations/'.$id.'/messages', ['body' => 'Unauthorized'])->assertForbidden();
        Event::assertNotDispatched(CommunicationChanged::class);
    }

    public function test_announcements_deliver_messages_only_to_selected_company(): void
    {
        Event::fake([CommunicationChanged::class]);
        $a = $this->employee();
        $b = $this->employee();
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Other realtime company', 'created_at' => now(), 'updated_at' => now()]);
        $b->forceFill(['company_id' => $other])->save();
        $this->withSession(['judibas_admin' => true])->postJson('/communication/api/announcements', ['company_ids' => [$a->company_id], 'body' => 'Company-only announcement'])->assertCreated();
        Event::assertDispatched(CommunicationChanged::class, fn ($e) => $e->kind === 'message' && $e->message['body'] === 'Company-only announcement' && in_array($a->id, $e->recipients) && ! in_array($b->id, $e->recipients));
        Event::assertDispatchedTimes(CommunicationChanged::class, 1);
    }

    public function test_revoked_accounts_are_excluded_from_direct_message_payloads(): void
    {
        Event::fake([CommunicationChanged::class]);
        $a = $this->employee();
        $revoked = $this->employee();
        $id = DB::table('communication_conversations')->insertGetId(['kind' => 'group', 'name' => 'Revocation test', 'company_id' => $a->company_id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('communication_members')->insert([['conversation_id' => $id, 'user_id' => $a->id], ['conversation_id' => $id, 'user_id' => $revoked->id]]);
        DB::table('user_product_access')->where('user_id', $revoked->id)->where('product_slug', 'communication')->delete();
        $this->actingAs($a)->postJson('/communication/api/conversations/'.$id.'/messages', ['body' => 'Access-checked message'])->assertCreated();
        Event::assertDispatched(CommunicationChanged::class, fn ($e) => in_array($a->id, $e->recipients) && ! in_array($revoked->id, $e->recipients));
    }
}
