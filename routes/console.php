<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('judibas:admin-password', function () {
    $password = $this->secret('Choose an administrator password (at least 12 characters)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Use at least 12 characters.');

        return 1;
    }
    if ($password !== $this->secret('Confirm administrator password')) {
        $this->error('Passwords do not match.');

        return 1;
    }
    $path = base_path('.env');
    $contents = file_get_contents($path);
    $line = "JUDIBAS_ADMIN_PASSWORD_HASH='".Hash::make($password)."'";
    $contents = preg_match('/^JUDIBAS_ADMIN_PASSWORD_HASH=.*$/m', $contents) ? preg_replace_callback('/^JUDIBAS_ADMIN_PASSWORD_HASH=.*$/m', fn () => $line, $contents) : $contents.PHP_EOL.$line.PHP_EOL;
    file_put_contents($path, $contents);
    Artisan::call('config:clear');
    $this->info('Administrator password configured. Visit /admin to sign in.');
})->purpose('Set the password for the initial branding administrator');

Artisan::command('communication:prune-stories', function () {
    $count = 0;
    DB::table('communication_stories')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($stories) use (&$count) {
        foreach ($stories as $story) {
            if ($story->attachment_path) {
                \App\Services\CommunicationAttachmentStorage::delete($story->attachment_path);
            }
            DB::table('communication_stories')->where('id', $story->id)->delete();
            $count++;
        }
    });
    $this->info("Removed {$count} expired stories.");
})->purpose('Remove expired Communication stories and their private attachments');

Schedule::command('communication:prune-stories')->hourly();

Artisan::command('communication:sync-attachments', function () {
    if (config('communication_attachments.disk') !== 'r2') { $this->error('Enable R2 in .env first.'); return 1; }
    foreach (['communication_messages', 'communication_stories'] as $table) {
        DB::table($table)->whereNotNull('attachment_path')->when($table === 'communication_messages', fn ($q) => $q->whereNull('deleted_at'))
            ->when($table === 'communication_stories', fn ($q) => $q->where('expires_at', '>', now()))->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) \App\Services\CommunicationAttachmentStorage::queue($row->attachment_path);
            });
    }
    $this->info('Existing attachments queued for cloud backup. Run the attachment queue worker.');
});

Artisan::command('communication:storage-check', function () {
    $key = 'communication-check/'.\Illuminate\Support\Str::uuid();
    try {
        $disk = Storage::disk('r2');
        $disk->put($key, 'Judibas private storage check');
        if ($disk->get($key) !== 'Judibas private storage check') throw new \RuntimeException('Read verification failed');
        $this->info('R2 write/read verified.');
    } catch (\Throwable $e) { $this->error('R2 check failed. Verify credentials, bucket, endpoint and network.'); return 1; }
    finally { try { Storage::disk('r2')->delete($key); } catch (\Throwable $e) {} }
});
