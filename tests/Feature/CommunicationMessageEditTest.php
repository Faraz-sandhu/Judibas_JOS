<?php

namespace Tests\Feature;

use App\Events\CommunicationChanged;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommunicationMessageEditTest extends TestCase
{
    use DatabaseTransactions;

    private User $author;

    private User $recipient;

    private int $chat;

    private int $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
        $this->author = $this->person();
        $this->recipient = $this->person();
        $this->actingAs($this->author)->withSession(['judibas_admin' => false]);
        $this->chat = $this->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$this->recipient->id]])->assertOk()->json('id');
        $this->message = $this->postJson('/communication/api/conversations/'.$this->chat.'/messages', ['body' => 'Original delivery plan'])->assertCreated()->json('id');
    }

    private function person(bool $admin = false): User
    {
        $user = User::factory()->create(['is_active' => true, 'judibas_administrator' => $admin]);
        DB::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'communication']);

        return $user;
    }

    public function test_own_edits_are_versioned_and_history_is_returned_only_to_super_admin(): void
    {
        $url = '/communication/api/messages/'.$this->message;
        $this->patchJson($url, ['body' => 'Revised delivery plan'])->assertOk();
        $this->patchJson($url, ['body' => 'Final delivery plan'])->assertOk();
        foreach ([$this->author, $this->recipient] as $user) {
            $messages = $this->actingAs($user)->getJson('/communication/api/conversations/'.$this->chat.'/messages')->assertOk();
            $messages->assertJsonPath('messages.0.body', 'Final delivery plan')->assertJsonMissingPath('messages.0.edit_history');
        }
        $this->withSession(['judibas_admin' => true]);
        $this->getJson('/communication/api/conversations/'.$this->chat.'/messages')->assertOk()
            ->assertJsonCount(2, 'messages.0.edit_history')
            ->assertJsonPath('messages.0.edit_history.0.before', 'Original delivery plan')
            ->assertJsonPath('messages.0.edit_history.0.after', 'Revised delivery plan')
            ->assertJsonPath('messages.0.edit_history.1.before', 'Revised delivery plan')
            ->assertJsonPath('messages.0.edit_history.1.after', 'Final delivery plan')
            ->assertJsonPath('messages.0.edit_history.0.editor_name', $this->author->name);
        $row = DB::table('communication_message_edits')->where('message_id', $this->message)->orderBy('id')->first();
        $this->assertNotSame('Original delivery plan', $row->previous_body);
        $this->assertSame('Original delivery plan', Crypt::decryptString($row->previous_body));
        Event::assertDispatched(CommunicationChanged::class, fn ($event) => $event->kind === 'workspace' && $event->conversationId === $this->chat && $event->message === null);
    }

    public function test_other_users_and_review_mode_cannot_edit_and_plain_admin_does_not_receive_history(): void
    {
        $this->actingAs($this->recipient)->patchJson('/communication/api/messages/'.$this->message, ['body' => 'Not the author'])->assertForbidden();
        $this->actingAs($this->author)->patchJson('/communication/api/messages/'.$this->message, ['body' => 'Author correction'])->assertOk();
        $this->author->forceFill(['judibas_administrator' => true])->save();
        $this->getJson('/communication/api/conversations/'.$this->chat.'/messages')->assertOk()->assertJsonMissingPath('messages.0.edit_history');
        $this->withSession(['judibas_admin' => true]);
        $this->patchJson('/communication/api/messages/'.$this->message, ['body' => 'Cannot edit employee message'])->assertForbidden();
        $this->patchJson('/communication/api/messages/'.$this->message.'?view_user='.$this->author->id, ['body' => 'Review is read only'])->assertForbidden();
        $this->assertSame(1, DB::table('communication_message_edits')->where('message_id', $this->message)->count());
    }

    public function test_noop_blank_and_phone_edits_do_not_create_history(): void
    {
        $url = '/communication/api/messages/'.$this->message;
        $this->patchJson($url, ['body' => 'Original delivery plan'])->assertOk();
        $this->patchJson($url, ['body' => '   '])->assertUnprocessable();
        $this->patchJson($url, ['body' => 'Call 03001234567'])->assertUnprocessable();
        $this->assertDatabaseMissing('communication_message_edits', ['message_id' => $this->message]);
        $this->assertDatabaseHas('communication_messages', ['id' => $this->message, 'body' => 'Original delivery plan', 'edited_at' => null]);
    }

    public function test_protected_and_deleted_messages_remain_non_editable(): void
    {
        DB::table('communication_messages')->where('id', $this->message)->update(['protected_payload' => json_encode(['ciphertext' => 'Encrypted content'])]);
        $this->patchJson('/communication/api/messages/'.$this->message, ['body' => 'Must not become plaintext'])->assertUnprocessable();
        $this->withSession(['judibas_admin' => true])->getJson('/communication/api/conversations/'.$this->chat.'/messages')->assertOk()->assertJsonMissingPath('messages.0.edit_history');
        DB::table('communication_messages')->where('id', $this->message)->update(['protected_payload' => null, 'deleted_at' => now(), 'body' => null]);
        $this->withSession(['judibas_admin' => false])->patchJson('/communication/api/messages/'.$this->message, ['body' => 'Must not restore deleted message'])->assertForbidden();
        $this->assertDatabaseMissing('communication_message_edits', ['message_id' => $this->message]);
    }

    public function test_super_admin_can_edit_their_own_message_and_history_survives_soft_deletion(): void
    {
        $this->withSession(['judibas_admin' => true]);
        $group = DB::table('communication_conversations')->insertGetId(['kind' => 'group', 'name' => 'Edit audit test', 'created_at' => now(), 'updated_at' => now()]);
        $id = $this->postJson('/communication/api/conversations/'.$group.'/messages', ['body' => 'Admin original'])->assertCreated()->json('id');
        $this->patchJson('/communication/api/messages/'.$id, ['body' => 'Admin revised'])->assertOk();
        $this->deleteJson('/communication/api/messages/'.$id)->assertOk();
        $this->getJson('/communication/api/conversations/'.$group.'/messages')->assertOk()->assertJsonPath('messages.0.body',null)->assertJsonPath('messages.0.edit_history.0.before','Admin original');
    }
}
