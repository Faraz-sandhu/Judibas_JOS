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
}
