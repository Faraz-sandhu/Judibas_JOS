<?php

namespace Tests\Feature;

use App\Events\CommunicationEncryptedChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationEncryptedTest extends TestCase
{
    use DatabaseTransactions;

    private string $base = '/communication/api/encrypted';

    private int $initialDevices = 0;

    private int $initialMessages = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([CommunicationEncryptedChanged::class]);
        $this->initialDevices = DB::table('communication_e2ee_devices')->count();
        $this->initialMessages = DB::table('communication_e2ee_messages')->count();
    }

    private function user(array $attributes = []): User
    {
        $u = User::factory()->create(['is_active' => true] + $attributes);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);

        return $u;
    }

    private function key(): array
    {
        return ['id' => (string) Str::uuid(), 'public_key' => base64_encode(hex2bin('046b17d1f2e12c4247f8bce6e563a440f277037d812deb33a0f4a13945d898c2964fe342e2fe1a7f9b8ee7eb4a7c0f9e162bce33576b315ececbb6406837bf51f5'))];
    }

    private function register(User $u): array
    {
        $device = $this->key();
        $this->withSession(['judibas_admin' => false])->actingAs($u)->postJson($this->base.'/devices', $device)->assertOk();

        return $device;
    }

    private function setupThread(): array
    {
        $peer = $this->user();
        $pd = $this->register($peer);
        $super = (array) (DB::table('communication_e2ee_devices')->where('principal', 'super')->first(['id', 'public_key']) ?? (object) $this->key());
        $this->withSession(['judibas_admin' => true])->postJson($this->base.'/devices', $super)->assertOk();
        $id = $this->postJson($this->base.'/threads', ['peer_user_id' => $peer->id, 'peer_device_id' => $pd['id']])->assertOk()->json('id');

        return [$peer, $pd, $super, $id];
    }

    private function envelope(array $devices): array
    {
        return ['id' => (string) Str::uuid(), 'envelopes' => array_map(fn ($d) => ['device_id' => $d['id'], 'enc' => $d['public_key'], 'ciphertext' => base64_encode(random_bytes(64))], $devices)];
    }

    public function test_only_actual_super_admin_can_create_encrypted_chats(): void
    {
        foreach ([[], ['communication_admin' => true], ['judibas_administrator' => true]] as $attributes) {
            $user = $this->user($attributes);
            $this->withSession(['judibas_admin' => false])->actingAs($user)->postJson($this->base.'/threads', ['peer_user_id' => $user->id, 'peer_device_id' => (string) Str::uuid()])->assertForbidden();
        }
        [$peer,$pd,$super,$id] = $this->setupThread();
        $this->assertDatabaseHas('communication_e2ee_threads', ['id' => $id, 'peer_user_id' => $peer->id]);
        $this->getJson($this->base)->assertJsonPath('can_create', true);
    }

    public function test_device_keys_cannot_be_replaced_and_private_material_is_not_stored(): void
    {
        $u = $this->user();
        $key = $this->register($u);
        $this->postJson($this->base.'/devices', $key)->assertOk();
        $this->postJson($this->base.'/devices', $key + ['private_key' => 'must-never-be-uploaded'])->assertUnprocessable();
        $this->postJson($this->base.'/devices', $this->key())->assertStatus(409);
        $this->postJson($this->base.'/devices', ['id' => (string) Str::uuid(), 'public_key' => base64_encode(str_repeat('x', 65))])->assertUnprocessable();
        $this->assertDatabaseCount('communication_e2ee_devices', $this->initialDevices + 1);
        $stored = (array) DB::table('communication_e2ee_devices')->first();
        $this->assertEqualsCanonicalizing(['id', 'principal', 'public_key', 'created_at'], array_keys($stored));
    }

    public function test_participants_only_even_for_administrators_and_review_requests(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        $third = $this->user(['judibas_administrator' => true]);
        $this->register($third);
        $this->getJson($this->base)->assertJsonCount(0, 'threads')->assertJsonCount(0, 'people')->assertJsonPath('can_create', false);
        $this->getJson($this->base.'/threads/'.$id.'/messages')->assertNotFound();
        $this->postJson($this->base.'/threads/'.$id.'/messages', $this->envelope([$pd, $super]))->assertNotFound();
        $this->withSession(['judibas_admin' => true])->getJson($this->base.'?view_user='.$peer->id)->assertForbidden();
        $this->getJson($this->base.'/threads/'.$id.'/messages?mode=review')->assertForbidden();
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->getJson($this->base)->assertJsonCount(1, 'threads');
    }

    public function test_ciphertext_send_is_idempotent_and_has_no_normal_chat_or_review_copy(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        $normal = DB::table('communication_messages')->count();
        $body = $this->envelope([$pd, $super]);
        $this->postJson($this->base.'/threads/'.$id.'/messages', $body)->assertOk();
        $this->postJson($this->base.'/threads/'.$id.'/messages', $body)->assertOk();
        $this->assertDatabaseCount('communication_e2ee_messages', $this->initialMessages + 1);
        $this->assertSame($normal, DB::table('communication_messages')->count());
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->getJson($this->base.'/threads/'.$id.'/messages')->assertOk()->assertJsonPath('messages.0.id', $body['id']);
        $changed = $body;
        $changed['envelopes'][0]['ciphertext'] = base64_encode(random_bytes(64));
        $this->postJson($this->base.'/threads/'.$id.'/messages', $changed)->assertStatus(409);
        $this->postJson($this->base.'/threads/'.$id.'/messages', $this->envelope([$pd, $super]))->assertOk();
        Event::assertDispatched(CommunicationEncryptedChanged::class);
        $event = new CommunicationEncryptedChanged($peer->id, $id);
        $this->assertSame(['thread_id' => $id], $event->broadcastWith());
        $this->assertCount(2, $event->broadcastOn());
        $this->withSession(['judibas_admin' => true])->getJson('/communication/api?mode=review')->assertOk()->assertDontSee($body['envelopes'][0]['ciphertext']);
    }

    public function test_plaintext_extra_fields_wrong_devices_and_malformed_envelopes_are_rejected(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        $valid = $this->envelope([$pd, $super]);
        $this->postJson($this->base.'/threads/'.$id.'/messages', $valid + ['body' => 'Secret salary'])->assertUnprocessable();
        $wrong = $valid;
        $wrong['envelopes'][0]['device_id'] = (string) Str::uuid();
        $this->postJson($this->base.'/threads/'.$id.'/messages', $wrong)->assertUnprocessable();
        $wrong = $valid;
        $wrong['envelopes'][0]['enc'] = 'invalid';
        $this->postJson($this->base.'/threads/'.$id.'/messages', $wrong)->assertUnprocessable();
        $this->assertDatabaseCount('communication_e2ee_messages', $this->initialMessages);
    }

    public function test_revoked_product_access_and_inactive_accounts_are_denied(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        DB::table('user_product_access')->where('user_id', $peer->id)->delete();
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->getJson($this->base)->assertForbidden();
        $this->withSession(['judibas_admin' => true])->postJson($this->base.'/threads', ['peer_user_id' => $peer->id, 'peer_device_id' => $pd['id']])->assertUnprocessable();
    }

    public function test_recipient_can_reply_but_cannot_add_participants_or_delete_history(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->postJson($this->base.'/threads/'.$id.'/messages', $this->envelope([$pd, $super]))->assertOk();
        $this->postJson($this->base.'/threads', ['peer_user_id' => $peer->id, 'peer_device_id' => $pd['id']])->assertForbidden();
        $this->withSession(['judibas_admin' => true])->deleteJson('/admin/api/users/'.$peer->id)->assertUnprocessable();
    }

    public function test_messages_paginate_in_server_sequence_order(): void
    {
        [$peer,$pd,$super,$id] = $this->setupThread();
        $bodies = [];
        for ($i = 0; $i < 27; $i++) {
            $body = $this->envelope([$pd, $super]);
            $bodies[] = $body;
            $this->postJson($this->base.'/threads/'.$id.'/messages', $body)->assertOk();
        }$page = $this->getJson($this->base.'/threads/'.$id.'/messages')->assertOk()->assertJsonPath('more', true)->assertJsonCount(25, 'messages')->json('messages');
        $this->assertSame($bodies[2]['id'], $page[0]['id']);
        $this->assertSame($bodies[26]['id'], $page[24]['id']);
        $this->getJson($this->base.'/threads/'.$id.'/messages?before='.$page[0]['id'])->assertOk()->assertJsonCount(2, 'messages')->assertJsonPath('messages.0.id', $bodies[0]['id']);
    }

    public function test_super_can_invite_an_employee_before_device_setup(): void
    {
        $peer = $this->user();
        $other = $this->user();
        $super = (array) (DB::table('communication_e2ee_devices')->where('principal', 'super')->first(['id', 'public_key']) ?? (object) $this->key());
        $this->withSession(['judibas_admin' => true])->postJson($this->base.'/devices', $super)->assertOk();
        $this->getJson($this->base)->assertOk()->assertJsonFragment(['id' => $peer->id, 'name' => $peer->name]);
        $id = $this->postJson($this->base.'/threads', ['peer_user_id' => $peer->id])->assertOk()->json('id');
        $this->postJson($this->base.'/threads', ['peer_user_id' => $peer->id])->assertOk()->assertJsonPath('id', $id);
        $this->getJson($this->base.'/threads/'.$id.'/messages')->assertStatus(409);
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->getJson($this->base)->assertOk()->assertJsonPath('threads.0.id', $id)->assertJsonPath('threads.0.peer_device_id', null);
        $this->actingAs($other)->getJson($this->base)->assertJsonCount(0, 'threads');
        $pd = $this->register($peer);
        $this->getJson($this->base)->assertJsonPath('threads.0.peer_device_id', $pd['id']);
        $this->postJson($this->base.'/threads/'.$id.'/messages', $this->envelope([$super, $pd]))->assertOk();
    }

    public function test_call_signals_are_ciphertext_only_participant_scoped_and_expire(): void
    {
        config(['communication_calls.enabled' => true]);
        [$peer,$pd,$super,$id] = $this->setupThread();
        $body = $this->envelope([$super, $pd]);
        $calls = DB::table('communication_calls')->count();
        $this->postJson($this->base.'/threads/'.$id.'/signals', $body + ['sdp' => 'plaintext'])->assertUnprocessable();
        $this->postJson($this->base.'/threads/'.$id.'/signals', $body)->assertOk();
        Event::assertDispatched(CommunicationEncryptedChanged::class, fn ($event) => ($event->broadcastWith()['signal_id'] ?? null) === $body['id']);
        $this->postJson($this->base.'/threads/'.$id.'/signals', $body)->assertOk();
        $url = $this->base.'/threads/'.$id.'/signals/'.$body['id'];
        $this->withSession(['judibas_admin' => false])->actingAs($peer)->getJson($url)->assertOk()->assertJsonPath('signal.id', $body['id']);
        $other = $this->user();
        $this->register($other);
        $this->getJson($url)->assertNotFound();
        $this->withSession(['judibas_admin' => true])->getJson($url.'?view_user='.$peer->id)->assertForbidden();
        DB::table('communication_e2ee_signals')->where('id', $body['id'])->update(['expires_at' => now()->subSecond()]);
        $this->getJson($url)->assertNotFound();
        $this->assertSame($calls, DB::table('communication_calls')->count());
        $event = new CommunicationEncryptedChanged($peer->id, $id, $body['id']);
        $this->assertSame(['thread_id' => $id, 'signal_id' => $body['id']], $event->broadcastWith());
    }

    public function test_anonymous_cannot_register_or_read_keys(): void
    {
        $this->getJson($this->base)->assertForbidden();
        $this->postJson($this->base.'/devices', $this->key())->assertForbidden();
    }
}
