<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MirrorCommunicationAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public string $path, public bool $delete = false) {}

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(): void
    {
        $referenced = DB::table('communication_messages')->where('attachment_path', $this->path)->whereNull('deleted_at')->exists()
            || DB::table('communication_stories')->where('attachment_path', $this->path)->where('expires_at', '>', now())->exists();
        $remote = Storage::disk('r2');
        if ($this->delete || ! $referenced) {
            $remote->delete($this->path);

            return;
        }
        $local = Storage::disk('local');
        if (! $local->exists($this->path)) {
            throw new \RuntimeException('Local attachment missing; cloud sync stopped.');
        }
        $stream = $local->readStream($this->path);
        try {
            if (! $remote->put($this->path, $stream, ['ContentType' => $local->mimeType($this->path)])) {
                throw new \RuntimeException('Cloud attachment upload failed.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $local->put($this->path.'.r2-ready', '1');
        // A deletion may have occurred while the upload was in progress.
        if (! DB::table('communication_messages')->where('attachment_path', $this->path)->whereNull('deleted_at')->exists()
            && ! DB::table('communication_stories')->where('attachment_path', $this->path)->where('expires_at', '>', now())->exists()) {
            $remote->delete($this->path);
            $local->delete($this->path.'.r2-ready');
        }
    }
}
