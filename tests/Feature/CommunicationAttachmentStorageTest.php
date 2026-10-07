<?php

namespace Tests\Feature;

use App\Jobs\MirrorCommunicationAttachment;
use App\Services\CommunicationAttachmentStorage as Attachments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunicationAttachmentStorageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::persistentFake('r2');
        config(['communication_attachments.disk' => 'r2']);
    }

    private function story(string $path): void
    {
        DB::table('communication_stories')->insert(['title' => 'Status', 'body' => '', 'attachment_path' => $path, 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_upload_is_queued_and_local_copy_remains_available(): void
    {
        Queue::fake();
        Storage::disk('local')->put('communication/test.txt', 'hello');
        Attachments::queue('communication/test.txt');
        Queue::assertPushed(MirrorCommunicationAttachment::class, fn ($job) => $job->queue === 'attachments' && $job->connection === 'database');
        $this->assertTrue(Attachments::available('communication/test.txt'));
        Storage::disk('r2')->assertMissing('communication/test.txt');
    }

    public function test_worker_mirrors_and_missing_local_file_recovers(): void
    {
        $path = 'communication/restore.txt';
        $this->story($path);
        Storage::disk('local')->put($path, 'hello');
        (new MirrorCommunicationAttachment($path))->handle();
        $this->assertSame('hello', Storage::disk('r2')->get($path));
        Storage::disk('local')->delete($path);
        $this->assertTrue(Attachments::available($path));
        $this->assertSame('hello', Storage::disk('local')->get($path));
    }

    public function test_deleted_upload_is_not_recreated_by_delayed_worker(): void
    {
        $path = 'communication/deleted.txt';
        Storage::disk('r2')->put($path, 'old');
        (new MirrorCommunicationAttachment($path))->handle();
        Storage::disk('r2')->assertMissing($path);
    }

    public function test_deletion_queues_remote_cleanup_and_removes_local_file(): void
    {
        Queue::fake();
        Storage::disk('local')->put('communication/delete.txt', 'hello');
        Attachments::delete('communication/delete.txt');
        Storage::disk('local')->assertMissing('communication/delete.txt');
        Queue::assertPushed(MirrorCommunicationAttachment::class, fn ($job) => $job->delete);
    }

    public function test_local_mode_does_not_enqueue_cloud_work(): void
    {
        Queue::fake();
        config(['communication_attachments.disk' => 'local']);
        Attachments::queue('communication/local.txt');
        Queue::assertNothingPushed();
    }

    public function test_cloud_outage_keeps_local_file_available(): void
    {
        Storage::disk('local')->put('communication/safe.txt', 'safe');
        $local = Storage::disk('local');
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('r2')->never();
        $this->assertTrue(Attachments::available('communication/safe.txt'));
    }

    public function test_failed_cloud_upload_preserves_local_backup_and_is_not_marked_ready(): void
    {
        $path = 'communication/failure.txt';
        $this->story($path);
        $local = Storage::disk('local');
        $local->put($path, 'safe');
        $remote = \Mockery::mock();
        $remote->shouldReceive('put')->once()->andThrow(new \RuntimeException('Cloud offline'));
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('r2')->andReturn($remote);
        try {
            (new MirrorCommunicationAttachment($path))->handle();
            $this->fail('Expected retryable cloud failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Cloud offline', $e->getMessage());
        }
        $this->assertSame('safe', $local->get($path));
        $local->assertMissing($path.'.r2-ready');
    }

    public function test_only_confirmed_cloud_previews_redirect_and_local_retry_bypasses_cloud(): void
    {
        $path = 'communication/preview.jpg';
        $local = Storage::disk('local');
        $this->assertNull(Attachments::preview($path, Request::create('/')));
        $local->put($path.'.r2-ready', '1');
        $remote = \Mockery::mock();
        $remote->shouldReceive('temporaryUrl')->once()->with($path, \Mockery::any())->andReturn('https://private.example.test/signed');
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('r2')->andReturn($remote);
        $this->assertSame('https://private.example.test/signed', Attachments::preview($path, Request::create('/'))->getTargetUrl());
        $this->assertNull(Attachments::preview($path, Request::create('/?local=1')));
    }
}
