<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StoryViewerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function employee(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);

        return $u;
    }

    private function story(): int
    {
        return $this->withSession(['judibas_admin' => true])->postJson('/communication/api/stories', ['title' => 'Viewer test', 'body' => 'Hello'])->assertCreated()->json('id');
    }

    public function test_views_are_unique_recorded_on_open_and_admin_only(): void
    {
        $id = $this->story();
        $u = $this->employee();
        $this->getJson("/communication/api/stories/$id/viewers")->assertJsonPath('total', 0);
        $this->withSession(['judibas_admin' => false])->actingAs($u)->getJson('/communication/api')->assertOk();
        $this->assertSame(0, DB::table('communication_story_views')->where('story_id', $id)->count());
        $this->postJson("/communication/api/stories/$id/view")->assertOk();
        $first = DB::table('communication_story_views')->where('story_id', $id)->first()->first_viewed_at;
        $this->travel(1)->minutes();
        $this->postJson("/communication/api/stories/$id/view")->assertOk();
        $view = DB::table('communication_story_views')->where('story_id', $id)->first();
        $this->assertSame($first, $view->first_viewed_at);
        $this->assertNotSame($first, $view->last_viewed_at);
        $this->assertSame(1, DB::table('communication_story_views')->where('story_id', $id)->count());
        $this->getJson("/communication/api/stories/$id/viewers")->assertForbidden();
        $this->withSession(['judibas_admin' => true])->getJson("/communication/api/stories/$id/viewers")->assertJsonPath('total', 1)->assertJsonPath('viewers.0.name', $u->name)->assertJsonMissingPath('viewers.0.profile_photo_path');
        $this->postJson("/communication/api/stories/$id/view?view_user=$u->id")->assertForbidden();
        $this->deleteJson("/communication/api/stories/$id")->assertOk();
        $this->assertDatabaseMissing('communication_story_views', ['story_id' => $id]);
    }

    public function test_expired_stories_and_inactive_accounts_cannot_record_views(): void
    {
        $id = $this->story();
        $u = $this->employee();
        $u->is_active = false;
        $u->save();
        $this->withSession(['judibas_admin' => false])->actingAs($u)->postJson("/communication/api/stories/$id/view")->assertForbidden();
        $u->is_active = true;
        $u->save();
        $this->travel(25)->hours();
        $this->actingAs($u)->postJson("/communication/api/stories/$id/view")->assertNotFound();
        $this->withSession(['judibas_admin' => true])->getJson("/communication/api/stories/$id/viewers")->assertNotFound();
    }

    public function test_viewer_lists_are_paginated_without_duplicate_people(): void
    {
        $id = $this->story();
        $users = User::factory()->count(31)->create();
        foreach ($users as $u) {
            DB::table('communication_story_views')->insert(['story_id' => $id, 'user_id' => $u->id, 'first_viewed_at' => now(), 'last_viewed_at' => now()]);
        }
        $first = $this->getJson("/communication/api/stories/$id/viewers")->assertJsonCount(30, 'viewers')->assertJsonPath('total', 31)->assertJsonPath('next_page', 2)->json('viewers');
        $second = $this->getJson("/communication/api/stories/$id/viewers?page=2")->assertJsonCount(1, 'viewers')->assertJsonPath('next_page', null)->json('viewers');
        $this->assertNotContains($second[0]['id'], array_column($first,'id'));
    }
}
