<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function employee(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);

        return $u;
    }

    private function direct(User $a, User $b): int
    {
        return $this->actingAs($a)->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$b->id]])->assertOk()->json('id');
    }

    public function test_shared_content_is_scoped_paginated_and_excludes_deleted_files(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $id = $this->direct($a, $b);
        $link = $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Visit https://example.com/docs and https://example.com/docs'])->assertCreated()->json('id');
        $doc = $this->post("/communication/api/conversations/$id/messages", ['attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Company document')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $media = DB::table('communication_messages')->insertGetId(['conversation_id' => $id, 'sender_id' => $a->id, 'sender_name' => $a->name, 'attachment_name' => 'photo.PNG', 'attachment_path' => 'communication/private.png', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson("/communication/api/conversations/$id/shared?kind=links")->assertOk()->assertJsonPath('items.0.id', $link)->assertJsonPath('items.0.links', ['https://example.com/docs']);
        $this->getJson("/communication/api/conversations/$id/shared?kind=docs")->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $doc)->assertJsonMissingPath('items.0.attachment_path');
        $this->getJson("/communication/api/conversations/$id/shared?kind=media")->assertOk()->assertJsonPath('items.0.id', $media)->assertJsonCount(2, 'members');
        $this->getJson("/communication/api/conversations/$id/shared?kind=invalid")->assertUnprocessable();
        $this->actingAs($outsider)->getJson("/communication/api/conversations/$id/shared?kind=docs")->assertForbidden();
        $this->withSession(['judibas_admin' => true])->deleteJson("/communication/api/messages/$doc")->assertOk();
        $this->getJson("/communication/api/conversations/$id/shared?kind=docs")->assertJsonCount(0, 'items');
        $this->withSession(['judibas_admin' => false])->actingAs($a);
        for ($i = 0; $i < 32; $i++) {
            DB::table('communication_messages')->insert(['conversation_id' => $id, 'sender_id' => $a->id, 'sender_name' => $a->name, 'attachment_name' => "file$i.txt", 'attachment_path' => "communication/file$i.txt", 'created_at' => now(), 'updated_at' => now()]);
        }
        $page = $this->getJson("/communication/api/conversations/$id/shared?kind=docs")->assertJsonCount(30, 'items')->assertJsonPath('has_more', true)->json();
        $this->getJson("/communication/api/conversations/$id/shared?kind=docs&before=".$page['before'])->assertJsonCount(2, 'items')->assertJsonPath('has_more', false);
    }

    public function test_search_results_can_open_older_message_context_without_cross_chat_access(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $id = $this->direct($a, $b);
        $target = $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Older milestone'])->assertCreated()->json('id');
        for ($i = 0; $i < 65; $i++) {
            DB::table('communication_messages')->insert(['conversation_id' => $id, 'sender_id' => $a->id, 'sender_name' => $a->name, 'body' => "Newer message $i", 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->getJson("/communication/api/conversations/$id/messages?search=milestone")->assertJsonPath('messages.0.id', $target);
        $this->getJson("/communication/api/conversations/$id/messages?around=$target")->assertOk()->assertJsonPath('messages.0.id', $target)->assertJsonCount(26, 'messages');
        $other = $this->direct($a, $this->employee());
        $this->getJson("/communication/api/conversations/$other/messages?around=$target")->assertNotFound();
    }

    public function test_product_access_is_required(): void
    {
        $this->get('/communication')->assertRedirect('/login');
        $this->getJson('/communication/api')->assertForbidden();
        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u)->getJson('/communication/api')->assertForbidden();
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);
        $this->get('/communication')->assertOk();
        $u->is_active = false;
        $u->save();
        $this->getJson('/communication/api')->assertForbidden();
    }

    public function test_direct_messages_are_private_and_reuse_the_same_conversation(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $id = $this->direct($a, $b);
        $this->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$b->id]])->assertJsonPath('id', $id);
        $mid = $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Hello colleague'])->assertCreated()->json('id');
        $this->actingAs($outsider)->getJson("/communication/api/conversations/$id/messages")->assertForbidden();
        $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Intrusion'])->assertForbidden();
        $this->getJson('/communication/api')->assertJsonMissing(['id' => $id, 'kind' => 'direct']);
        $this->actingAs($b)->getJson('/communication/api')->assertJsonPath('conversations.0.unread', 1);
        $this->getJson("/communication/api/conversations/$id/messages")->assertJsonPath('messages.0.body', 'Hello colleague');
        $this->getJson('/communication/api')->assertJsonPath('conversations.0.unread', 0);
        $this->patchJson("/communication/api/messages/$mid", ['body' => 'Not my message'])->assertForbidden();
        $this->actingAs($a)->patchJson("/communication/api/messages/$mid", ['body' => 'Updated'])->assertOk();
        $this->deleteJson("/communication/api/messages/$mid")->assertForbidden();
        $this->withSession(['judibas_admin' => true])->deleteJson("/communication/api/messages/$mid")->assertOk();
        $this->getJson("/communication/api/conversations/$id/messages")->assertJsonPath('messages.0.body', null);
    }

    public function test_group_membership_can_be_revoked_after_messages_were_read(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $company = DB::table('users')->where('id', $a->id)->value('company_id');
        $this->withSession(['judibas_admin' => true]);
        $id = $this->postJson('/communication/api/conversations', ['kind' => 'group', 'company_id' => $company, 'name' => 'Engineering', 'member_ids' => [$a->id, $b->id]])->assertOk()->json('id');
        $this->withSession(['judibas_admin' => false])->actingAs($a)->getJson("/communication/api/conversations/$id/messages")->assertOk();
        $this->actingAs($outsider)->getJson("/communication/api/conversations/$id/messages")->assertForbidden();
        $this->withSession(['judibas_admin' => true])->postJson("/communication/api/groups/$id", ['name' => 'Engineering', 'member_ids' => [$b->id]])->assertOk();
        $this->withSession(['judibas_admin' => false])->actingAs($a)->getJson("/communication/api/conversations/$id/messages")->assertForbidden();
        $this->actingAs($b)->getJson("/communication/api/conversations/$id/messages")->assertOk();
    }

    public function test_administrator_reviews_are_scoped_read_only_and_audited(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $other = $this->employee();
        $id = $this->direct($a, $b);
        $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Company message'])->assertCreated();
        $this->postJson('/communication/api/review', ['view_user' => $b->id])->assertForbidden();
        $this->getJson('/communication/api?view_user='.$b->id)->assertForbidden();
        $this->withSession(['judibas_admin' => true])->postJson('/communication/api/review', ['view_user' => $b->id])->assertOk()->assertJsonFragment(['id' => $id, 'kind' => 'direct']);
        $this->getJson("/communication/api/conversations/$id/messages?view_user={$b->id}&audit=1")->assertOk()->assertJsonPath('messages.0.body', 'Company message');
        $this->assertDatabaseHas('communication_audits', ['viewed_user_id' => $b->id, 'conversation_id' => $id, 'action' => 'conversation_read']);
        $this->getJson("/communication/api/conversations/$id/messages?view_user={$other->id}")->assertForbidden();
        $this->postJson("/communication/api/conversations/$id/messages?view_user={$b->id}", ['body' => 'Impersonation'])->assertForbidden();
        $this->getJson('/communication/api/audits')->assertOk();
        $this->assertAuthenticatedAs($a);
    }

    public function test_attachments_are_private_and_stories_are_admin_only(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $id = $this->direct($a, $b);
        $mid = $this->post("/communication/api/conversations/$id/messages", ['attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Company document')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->get("/communication/api/attachments/$mid")->assertOk();
        $this->actingAs($outsider)->get("/communication/api/attachments/$mid")->assertForbidden();
        $this->postJson('/communication/api/stories', ['title' => 'Illegal', 'body' => 'No access'])->assertForbidden();
        $storyId = $this->withSession(['judibas_admin' => true])->postJson('/communication/api/stories', ['title' => 'Company update', 'body' => 'A new announcement'])->assertCreated()->json('id');
        $this->withSession(['judibas_admin' => false])->getJson('/communication/api')->assertJsonFragment(['id' => $storyId, 'title' => 'Company update']);
        $this->travel(25)->hours();
        $this->getJson('/communication/api')->assertJsonMissing(['id' => $storyId, 'title' => 'Company update']);
    }

    public function test_groups_replies_search_and_pagination(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $id = $this->actingAs($a)->postJson('/communication/api/conversations', ['kind' => 'group', 'name' => 'Project Alpha', 'member_ids' => [$b->id]])->assertOk()->json('id');
        $first = $this->postJson("/communication/api/conversations/$id/messages", ['body' => 'Searchable milestone'])->assertCreated()->json('id');
        $this->actingAs($b)->postJson("/communication/api/conversations/$id/messages", ['body' => 'Reply', 'reply_to' => $first])->assertCreated();
        $other = $this->direct($a, $b);
        $this->postJson("/communication/api/conversations/$other/messages", ['body' => 'Invalid reply', 'reply_to' => $first])->assertUnprocessable();
        for ($i = 0; $i < 51; $i++) {
            DB::table('communication_messages')->insert(['conversation_id' => $id, 'sender_id' => $a->id, 'sender_name' => $a->name, 'body' => 'Message '.$i, 'created_at' => now(), 'updated_at' => now()]);
        }
        $latest = $this->getJson("/communication/api/conversations/$id/messages")->assertJsonCount(50, 'messages')->assertJsonPath('has_more', true)->json('messages');
        $this->getJson("/communication/api/conversations/$id/messages?before=".$latest[0]['id'])->assertJsonCount(3, 'messages');
        $this->getJson("/communication/api/conversations/$id/messages?search=Searchable")->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.id', $first);
    }
}
