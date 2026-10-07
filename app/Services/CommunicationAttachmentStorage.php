<?php

namespace App\Services;

use App\Jobs\MirrorCommunicationAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunicationAttachmentStorage
{
    public static function store(UploadedFile $file, string $directory): string
    {
        try {
            $path = $file->store($directory, 'local');
        } catch (\Throwable $e) {
            $path = false;
        }
        if (! $path) {
            throw ValidationException::withMessages(['attachment' => 'Unable to save attachment. Check server storage and try again.']);
        }

        return $path;
    }

    public static function preview(string $path, Request $request)
    {
        if ($request->boolean('local') || config('communication_attachments.disk') !== 'r2' || ! Storage::disk('local')->exists($path.'.r2-ready')) {
            return null;
        }
        try {
            return redirect()->away(Storage::disk('r2')->temporaryUrl($path, now()->addMinutes(5)))->header('Cache-Control', 'private, no-store');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function available(string $path): bool
    {
        $local = Storage::disk('local');
        if ($local->exists($path)) {
            return true;
        }
        if (config('communication_attachments.disk') !== 'r2') {
            return false;
        }
        try {
            $stream = Storage::disk('r2')->readStream($path);
            if (! is_resource($stream)) {
                return false;
            }
            try {
                return $local->put($path, $stream);
            } finally {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            Log::warning('Attachment recovery unavailable.', ['exception' => get_class($e)]);

            return false;
        }
    }

    public static function queue(?string $path): void
    {
        if (! $path || config('communication_attachments.disk') !== 'r2') {
            return;
        }
        try {
            MirrorCommunicationAttachment::dispatch($path)->onConnection('database')->onQueue('attachments');
        } catch (\Throwable $e) {
            Log::warning('Attachment sync could not be queued; local copy retained.', ['exception' => get_class($e)]);
        }
    }

    public static function delete(string|array $paths): void
    {
        foreach ((array) $paths as $path) {
            if (! $path) {
                continue;
            }
            Storage::disk('local')->delete([$path, $path.'.r2-ready']);
            if (config('communication_attachments.disk') === 'r2') {
                try {
                    MirrorCommunicationAttachment::dispatch($path, true)->onConnection('database')->onQueue('attachments');
                } catch (\Throwable $e) {
                    Log::warning('Remote attachment cleanup could not be queued.', ['exception' => get_class($e)]);
                }
            }
        }
    }
}
