<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommunicationAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationActionsTest extends TestCase
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
        $u = User::factory()->create(['is_active' => true, 'password' => 'original-password-123']);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);

        return $u;
    }

    private function direct(User $a, User $b): int
    {
        return $this->actingAs($a)->postJson('/communication/api/conversations', ['kind' => 'direct', 'member_ids' => [$b->id]])->assertOk()->json('id');
    }

    public function test_profile_updates_only_self_and_requires_current_password(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $this->actingAs($a)->postJson('/communication/api/profile', ['name' => 'Updated name', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'current_password' => 'incorrect'])->assertUnprocessable();
        $this->assertTrue(Hash::check('original-password-123', $a->fresh()->password));
        $this->withSession(['_token' => 'old-session-token']);
        $response = $this->postJson('/communication/api/profile', ['name' => 'Updated name', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'current_password' => 'original-password-123', 'id' => $b->id, 'communication_admin' => true])->assertOk()->assertJsonPath('profile.name', 'Updated name');
        $this->assertNotSame('old-session-token', $response->json('csrf'));
        $this->assertSame(session()->token(), $response->json('csrf'));
        $this->assertTrue(Hash::check('new-password-123', $a->fresh()->password));
        $this->assertFalse($a->fresh()->communication_admin);
        $this->assertNotEquals('Updated name', $b->fresh()->name);
        $this->getJson('/communication/api')->assertJsonPath('profile.name', 'Updated name');
        $this->withSession(['judibas_admin' => true])->postJson('/communication/api/profile', ['name' => 'Other'])->assertForbidden();
    }

    public function test_profile_photos_are_private_validated_and_replaced(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $this->actingAs($a)->post('/communication/api/profile', ['name' => $a->name, 'photo' => UploadedFile::fake()->createWithContent('photo.png', $png)], ['Accept' => 'application/json'])->assertOk();
        $path = $a->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($path);
        $this->getJson('/communication/api')->assertJsonMissingPath('people.0.profile_photo_path');
        $this->get("/communication/api/avatars/$a->id")->assertOk();
        $company = DB::table('communication_companies')->insertGetId(['name' => 'Private profile test', 'created_at' => now(), 'updated_at' => now()]);
        $b->company_id = $company;
        $b->save();
        $this->actingAs($b)->get("/communication/api/avatars/$a->id")->assertForbidden();
        $this->actingAs($a)->post('/communication/api/profile', ['name' => $a->name, 'photo' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->postJson('/communication/api/profile', ['name' => $a->name, 'remove_photo' => true])->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_forwarding_checks_both_chats_and_preserves_attachment_copy(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $c = $this->employee();
        $source = $this->direct($a, $b);
        $target = $this->direct($a, $c);
        $mid = $this->post("/communication/api/conversations/$source/messages", ['body' => 'Forward this', 'attachment' => UploadedFile::fake()->createWithContent('file.txt', 'Independent copy')], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $copy = $this->postJson("/communication/api/messages/$mid/forward", ['conversation_id' => $target])->assertCreated()->json('id');
        $this->assertNotEquals(DB::table('communication_messages')->where('id', $mid)->value('attachment_path'), DB::table('communication_messages')->where('id', $copy)->value('attachment_path'));
        $this->getJson("/communication/api/conversations/$target/messages")->assertJsonPath('messages.0.forwarded', true);
        $this->actingAs($c)->postJson("/communication/api/messages/$mid/forward", ['conversation_id' => $target])->assertForbidden();
        $other = $this->direct($b, $c);
        $this->actingAs($a)->postJson("/communication/api/messages/$mid/forward", ['conversation_id' => $other])->assertForbidden();
        $this->withSession(['judibas_admin' => true])->deleteJson("/communication/api/messages/$mid")->assertOk();
        $this->withSession(['judibas_admin' => false])->actingAs($c)->get("/communication/api/attachments/$copy")->assertOk();
        $this->withSession(['judibas_admin' => true])->postJson("/communication/api/messages/$copy/forward?view_user=$a->id", ['conversation_id' => $source])->assertForbidden();
    }

    public function test_pins_are_shared_limited_and_protected(): void
    {
        $a = $this->employee();
        $b = $this->employee();
        $outsider = $this->employee();
        $id = $this->direct($a, $b);
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = $this->postJson("/communication/api/conversations/$id/messages", ['body' => "Pin $i"])->assertCreated()->json('id');
        }
        foreach (array_slice($ids, 0, 3) as $mid) {
            $this->postJson("/communication/api/messages/$mid/pin", ['pinned' => true])->assertOk();
        }
        $this->postJson("/communication/api/messages/{$ids[3]}/pin", ['pinned' => true])->assertUnprocessable();
        $this->actingAs($b)->getJson("/communication/api/conversations/$id/messages")->assertJsonCount(3, 'pinned');
        $this->actingAs($outsider)->postJson("/communication/api/messages/{$ids[0]}/pin", ['pinned' => false])->assertForbidden();
        $this->actingAs($a)->postJson("/communication/api/messages/{$ids[0]}/pin", ['pinned' => false])->assertOk();
        $this->postJson("/communication/api/messages/{$ids[3]}/pin", ['pinned' => true])->assertOk();
        $company = DB::table('users')->where('id', $a->id)->value('company_id');
        $community = CommunicationAccess::community($company);
        $this->withSession(['judibas_admin' => true]);
        $announcement = $this->postJson("/communication/api/conversations/$community/messages", ['body' => 'Announcement'])->assertCreated()->json('id');
        $this->withSession(['judibas_admin' => false])->actingAs($a)->postJson("/communication/api/messages/$announcement/pin", ['pinned' => true])->assertForbidden();
        $this->postJson("/communication/api/messages/{$ids[1]}/forward", ['conversation_id' => $community])->assertForbidden();
    }
}
