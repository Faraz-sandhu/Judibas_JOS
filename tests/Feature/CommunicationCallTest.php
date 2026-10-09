<?php

namespace Tests\Feature;

use App\Events\CommunicationCallSignal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunicationCallTest extends TestCase
{
    use DatabaseTransactions;

    private User $a;

    private User $b;

    private User $outsider;

    private int $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['communication_calls.enabled' => true]);
        Event::fake([CommunicationCallSignal::class]);
        $company = DB::table('communication_companies')->insertGetId(['name' => 'Calling Test', 'created_at' => now(), 'updated_at' => now()]);
        $this->a = User::factory()->create(['company_id' => $company, 'is_active' => true]);
        $this->b = User::factory()->create(['company_id' => $company, 'is_active' => true]);
        $this->outsider = User::factory()->create(['company_id' => $company, 'is_active' => true]);
        foreach ([$this->a, $this->b, $this->outsider] as $u) {
            DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);
        }
        $this->chat = DB::table('communication_conversations')->insertGetId(['kind' => 'direct', 'name' => 'Calling Test', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$this->a, $this->b] as $u) {
            DB::table('communication_members')->insert(['conversation_id' => $this->chat, 'user_id' => $u->id]);
        }
    }

    private function start(): string
    {
        return $this->actingAs($this->a)->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => 'test offer']])->assertCreated()->json('call.id');
    }

    public function test_disabled_feature_blocks_all_calls_and_hides_snapshot_flag(): void
    {
        config(['communication_calls.enabled' => false]);
        $this->actingAs($this->a);
        $this->getJson('/communication/api')->assertOk()->assertJsonPath('calling_enabled', false);
        foreach (['/calls', '/calls/state', '/calls/ice'] as $path) {
            $this->getJson('/communication/api'.$path)->assertNotFound();
        }
        $this->postJson('/communication/api/calls', [])->assertNotFound();
    }

    public function test_voice_call_lifecycle_and_history_are_private(): void
    {
        $id = $this->start();
        $this->actingAs($this->b)->getJson('/communication/api/calls/state')->assertOk()->assertJsonPath('offer.sdp', 'test offer');
        $this->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'answer', 'answer' => ['type' => 'answer', 'sdp' => 'test answer']])->assertOk()->assertJsonPath('call.status', 'connecting');
        $this->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'connected'])->assertOk()->assertJsonPath('call.status', 'active');
        $this->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'ended'])->assertOk()->assertJsonPath('call.status', 'ended');
        $this->getJson('/communication/api/calls')->assertOk()->assertJsonCount(1, 'calls')->assertJsonMissing(['sdp' => 'test offer']);
        $this->actingAs($this->outsider)->getJson('/communication/api/calls')->assertOk()->assertJsonCount(0, 'calls');
        $this->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'heartbeat'])->assertForbidden();
        Event::assertDispatched(CommunicationCallSignal::class, fn ($e) => $e->recipient === $this->b->id && $e->payload['kind'] === 'incoming');
    }

    public function test_nonmembers_and_super_admin_cannot_call_or_get_turn_credentials(): void
    {
        $this->actingAs($this->outsider)->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => 'offer']])->assertForbidden();
        $this->withSession(['judibas_admin' => true])->getJson('/communication/api/calls/ice')->assertForbidden();
    }

    public function test_only_recipient_can_answer_and_busy_users_cannot_start_another_call(): void
    {
        $id = $this->start();
        $this->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'answer', 'answer' => ['type' => 'answer', 'sdp' => 'answer']])->assertForbidden();
        $this->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => 'offer']])->assertStatus(409);
        $this->actingAs($this->b)->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'rejected'])->assertOk()->assertJsonPath('call.status', 'rejected');
        $this->actingAs($this->a)->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'candidate', 'candidate' => ['candidate' => 'candidate']])->assertStatus(409);
    }

    public function test_unanswered_calls_expire_and_video_is_rejected(): void
    {
        $id = $this->start();
        $this->travel(61)->seconds();
        $this->getJson('/communication/api/calls/state')->assertOk()->assertJsonPath('call', null);
        $this->assertDatabaseHas('communication_calls', ['id' => $id, 'status' => 'missed']);
        $this->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'video', 'offer' => ['type' => 'offer', 'sdp' => 'offer']])->assertUnprocessable();
    }

    public function test_turn_secrets_stay_server_side_and_expiring_credentials_are_returned(): void
    {
        config(['communication_calls.turn_key_id' => 'test-key', 'communication_calls.turn_api_token' => 'server-secret']);
        Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [['urls' => ['turn:turn.cloudflare.com:3478'], 'username' => 'temporary-user', 'credential' => 'temporary-password']]], 201)]);
        $this->actingAs($this->a)->getJson('/communication/api/calls/ice')->assertOk()->assertJsonPath('relay', true)->assertJsonMissing(['turn_api_token' => 'server-secret']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer server-secret') && $r['ttl'] === 7200);
    }

    public function test_call_events_never_use_super_admin_channel(): void
    {
        $event = new CommunicationCallSignal($this->b->id, ['kind' => 'candidate']);
        $this->assertCount(1, $event->broadcastOn());
        $this->assertSame('private-communication.user.'.$this->b->id, $event->broadcastOn()[0]->name);
    }

    public function test_sdp_newlines_are_preserved_by_request_middleware(): void
    {
        $sdp = "v=0\r\na=sendrecv\r\n";
        $this->actingAs($this->a)->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => $sdp]])->assertCreated();
        Event::assertDispatched(CommunicationCallSignal::class, fn ($e) => $e->recipient === $this->b->id && ($e->payload['offer']['sdp'] ?? null) === $sdp);
    }

    public function test_turn_failure_returns_a_safe_error_without_secret_details(): void
    {
        config(['communication_calls.turn_key_id' => 'failed-key', 'communication_calls.turn_api_token' => 'private-server-secret']);
        Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['message' => 'private-server-secret'], 401)]);
        $this->actingAs($this->a)->getJson('/communication/api/calls/ice')->assertStatus(503)->assertJsonPath('message', 'Call relay unavailable. Check the Cloudflare TURN settings.')->assertDontSee('private-server-secret');
    }

    public function test_missed_calls_are_counted_and_read_status_is_private_and_persistent(): void
    {
        $id = $this->start();
        $this->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'cancelled'])->assertOk();
        $this->actingAs($this->b)->getJson('/communication/api/calls')->assertOk()->assertJsonPath('missed_unread', 1);
        $this->actingAs($this->outsider)->postJson('/communication/api/calls/read', ['ids' => [$id]])->assertOk();
        $this->assertDatabaseHas('communication_calls', ['id' => $id, 'callee_read_at' => null]);
        $this->actingAs($this->b)->postJson('/communication/api/calls/read', ['ids' => [$id]])->assertOk();
        $this->getJson('/communication/api/calls')->assertOk()->assertJsonPath('missed_unread', 0)->assertJsonPath('calls.0.unread', false);
        config(['communication_calls.enabled' => false]);
        $this->postJson('/communication/api/calls/read', ['ids' => [$id]])->assertNotFound();
    }

    public function test_chat_call_records_are_filtered_and_nonmember_access_is_denied(): void
    {
        $id = $this->start();
        $this->getJson('/communication/api/calls?conversation_id='.$this->chat)->assertOk()->assertJsonPath('calls.0.id', $id);
        $this->actingAs($this->outsider)->getJson('/communication/api/calls?conversation_id='.$this->chat)->assertForbidden();
    }

    public function test_calling_busy_recipient_records_missed_attempt_without_interrupting_active_call(): void
    {
        $active = $this->start();
        $otherChat = DB::table('communication_conversations')->insertGetId(['kind' => 'direct', 'name' => 'Busy Test', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$this->b, $this->outsider] as $u) {
            DB::table('communication_members')->insert(['conversation_id' => $otherChat, 'user_id' => $u->id]);
        }
        $response = $this->actingAs($this->outsider)->postJson('/communication/api/calls', ['conversation_id' => $otherChat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => 'offer']])->assertCreated()->assertJsonPath('call.status', 'busy');
        $this->assertDatabaseHas('communication_calls', ['id' => $active, 'status' => 'ringing']);
        $this->actingAs($this->b)->getJson('/communication/api/calls')->assertOk()->assertJsonPath('missed_unread', 1);
        Event::assertDispatched(CommunicationCallSignal::class, fn ($e) => $e->recipient === $this->b->id && $e->payload['kind'] === 'finished' && $e->payload['call']['id'] === $response->json('call.id'));
    }

    public function test_waiting_call_can_replace_active_call_and_unanswered_waiting_is_missed(): void
    {
        $active = $this->start();
        $this->actingAs($this->b)->postJson('/communication/api/calls/'.$active.'/signal', ['kind'=>'answer','answer'=>['type'=>'answer','sdp'=>'answer']])->assertOk();
        $this->postJson('/communication/api/calls/'.$active.'/signal', ['kind'=>'connected'])->assertOk();
        $chat = DB::table('communication_conversations')->insertGetId(['kind'=>'direct','name'=>'Waiting Test','created_at'=>now(),'updated_at'=>now()]);
        foreach ([$this->b, $this->outsider] as $u) DB::table('communication_members')->insert(['conversation_id'=>$chat,'user_id'=>$u->id]);
        $pending = $this->actingAs($this->outsider)->postJson('/communication/api/calls', ['conversation_id'=>$chat,'type'=>'audio','offer'=>['type'=>'offer','sdp'=>'waiting offer']])->assertCreated()->assertJsonPath('call.status','ringing')->assertJsonPath('call.waiting',true)->json('call.id');
        $this->assertDatabaseHas('communication_calls',['id'=>$active,'status'=>'active']);
        $this->actingAs($this->b)->postJson('/communication/api/calls/'.$pending.'/signal',['kind'=>'answer','answer'=>['type'=>'answer','sdp'=>'waiting answer']])->assertOk()->assertJsonPath('call.status','connecting');
        $this->assertDatabaseHas('communication_calls',['id'=>$active,'status'=>'ended']);
        $this->postJson('/communication/api/calls/'.$pending.'/finish',['status'=>'ended'])->assertOk();
        $missed = $this->actingAs($this->outsider)->postJson('/communication/api/calls',['conversation_id'=>$chat,'type'=>'audio','offer'=>['type'=>'offer','sdp'=>'offer']])->assertCreated()->json('call.id');
        DB::table('communication_calls')->where('id',$missed)->update(['created_at'=>now()->subSeconds(61)]);
        $this->actingAs($this->b)->getJson('/communication/api/calls')->assertOk()->assertJsonPath('missed_unread',1);
        $this->assertDatabaseHas('communication_calls',['id'=>$missed,'status'=>'missed']);
    }

    public function test_expiry_broadcasts_missed_outcome_for_live_badges(): void
    {
        $id = $this->start();
        $this->travel(61)->seconds();
        $this->getJson('/communication/api/calls')->assertOk();
        Event::assertDispatched(CommunicationCallSignal::class, fn ($e) => $e->recipient === $this->b->id && $e->payload['kind'] === 'finished' && $e->payload['call']['id'] === $id && $e->payload['call']['status'] === 'missed');
    }

    public function test_signaling_requests_do_not_consume_new_call_rate_limit(): void
    {
        $id = $this->start();
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/communication/api/calls/'.$id.'/signal', ['kind' => 'candidate', 'candidate' => ['candidate' => 'candidate'.$i]])->assertOk();
        }
        $this->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'cancelled'])->assertOk();
        $this->postJson('/communication/api/calls', ['conversation_id' => $this->chat, 'type' => 'audio', 'offer' => ['type' => 'offer', 'sdp' => 'offer']])->assertCreated();
    }

    public function test_super_admin_can_review_call_metadata_and_chat_history_but_cannot_mutate_calls(): void
    {
        $id = $this->start();
        $this->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'cancelled'])->assertOk();
        $this->withSession(['judibas_admin' => true]);
        $this->getJson('/communication/api')->assertOk()->assertJsonPath('calling_enabled', false)->assertJsonPath('call_history_enabled', true);
        $this->getJson('/communication/api/calls')->assertOk()->assertJsonPath('calls.0.id', $id)->assertJsonPath('missed_unread', 0)->assertJsonMissing(['sdp' => 'test offer']);
        $this->getJson('/communication/api/calls?view_user='.$this->b->id.'&conversation_id='.$this->chat)->assertOk()->assertJsonPath('calls.0.id', $id);
        $this->getJson('/communication/api/calls?view_user='.$this->outsider->id)->assertOk()->assertJsonCount(0, 'calls');
        $this->postJson('/communication/api/calls/read', ['ids' => [$id]])->assertForbidden();
        $this->postJson('/communication/api/calls/'.$id.'/finish', ['status' => 'ended'])->assertForbidden();
        $this->assertDatabaseHas('communication_calls', ['id' => $id, 'callee_read_at' => null]);
        $this->assertDatabaseHas('communication_audits', ['action' => 'call_history_review', 'viewed_user_id' => $this->b->id, 'conversation_id' => $this->chat]);
        config(['communication_calls.enabled' => false]);
        $this->getJson('/communication/api/calls')->assertNotFound();
    }

    public function test_employee_cannot_impersonate_another_user_in_call_history(): void
    {
        $this->actingAs($this->a)->getJson('/communication/api/calls?view_user='.$this->b->id)->assertForbidden();
    }
}
