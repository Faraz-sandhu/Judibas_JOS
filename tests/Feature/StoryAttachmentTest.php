<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryAttachmentTest extends TestCase
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

    public function test_delegated_admin_publishes_company_stories_with_private_viewers(): void
    {
        $company = DB::table('communication_companies')->insertGetId(['name' => 'Story test company', 'created_at' => now(), 'updated_at' => now()]);
        $manager = $this->employee();
        $manager->forceFill(['company_id' => $company, 'communication_admin' => true])->save();
        $peer = $this->employee();
        $peer->forceFill(['company_id' => $company])->save();
        $outsider = $this->employee();
        $id = $this->actingAs($manager)->post('/communication/api/stories', ['attachment' => $this->image()], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $this->assertDatabaseHas('communication_stories', ['id' => $id, 'author_id' => $manager->id, 'company_id' => $company]);
        $this->getJson("/communication/api/stories/$id/viewers")->assertOk();
        $this->deleteJson("/communication/api/stories/$id")->assertForbidden();
        $this->actingAs($peer)->get("/communication/api/stories/$id/attachment")->assertOk();
        $this->postJson("/communication/api/stories/$id/view")->assertOk();
        $this->getJson("/communication/api/stories/$id/viewers")->assertForbidden();
        $this->actingAs($outsider)->get("/communication/api/stories/$id/attachment")->assertNotFound();
        $this->postJson("/communication/api/stories/$id/view")->assertNotFound();
        $this->getJson('/communication/api')->assertJsonMissing(['title' => 'Status', 'author_id' => $manager->id]);
        $this->actingAs($manager)->getJson("/communication/api/stories/$id/viewers")->assertJsonPath('total', 1);
        $manager->forceFill(['communication_admin' => false])->save();
        $this->postJson('/communication/api/stories', ['title' => 'Blocked', 'body' => 'No'])->assertForbidden();
    }
    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('story.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }

    public function test_attachment_only_story_is_private_and_expires(): void
    {
        $id = $this->withSession(['judibas_admin' => true])->post('/communication/api/stories', ['title' => 'Photo story', 'attachment' => $this->image()], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $path = DB::table('communication_stories')->where('id', $id)->value('attachment_path');
        Storage::disk('local')->assertExists($path);
        $this->getJson('/communication/api')->assertJsonMissingPath('stories.0.attachment_path')->assertJsonPath('stories.0.attachment_mime', 'image/png');
        $this->withSession(['judibas_admin' => false])->get("/communication/api/stories/$id/attachment")->assertForbidden();
        $u = $this->employee();
        $this->actingAs($u)->get("/communication/api/stories/$id/attachment")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/communication/api/stories', ['title' => 'No access', 'attachment' => $this->image()], ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson("/communication/api/stories/$id")->assertForbidden();
        $this->travel(25)->hours();
        $this->get("/communication/api/stories/$id/attachment")->assertNotFound();
        $this->getJson('/communication/api')->assertJsonMissing(['id' => $id, 'title' => 'Photo story']);
        $this->artisan('communication:prune-stories')->assertExitCode(0);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('communication_stories', ['id' => $id]);
    }

    public function test_document_download_deletion_and_read_only_review(): void
    {
        $u = $this->employee();
        $id = $this->withSession(['judibas_admin' => true])->post('/communication/api/stories', ['title' => 'Document story', 'body' => 'Read this', 'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Private story document')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $path = DB::table('communication_stories')->where('id', $id)->value('attachment_path');
        $this->get("/communication/api/stories/$id/attachment?view_user=$u->id")->assertOk();
        $this->postJson("/communication/api/stories?view_user=$u->id", ['title' => 'Blocked', 'body' => 'No'])->assertForbidden();
        $this->deleteJson("/communication/api/stories/$id?view_user=$u->id")->assertForbidden();
        $this->deleteJson("/communication/api/stories/$id")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->get("/communication/api/stories/$id/attachment")->assertNotFound();
    }

    public function test_story_validation_keeps_text_support_and_rejects_unsafe_or_large_files(): void
    {
        $this->withSession(['judibas_admin' => true])->postJson('/communication/api/stories', ['body' => 'Still supported'])->assertCreated();
        $this->postJson('/communication/api/stories', ['body' => '   '])->assertUnprocessable();
        $this->post('/communication/api/stories', ['title' => 'Unsafe file', 'attachment' => UploadedFile::fake()->createWithContent('test.html', '<html>unsafe</html>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/communication/api/stories', ['title' => 'Large file', 'attachment' => UploadedFile::fake()->create('large.pdf', 21000, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
