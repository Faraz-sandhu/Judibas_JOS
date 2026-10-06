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
                Storage::disk('local')->delete($story->attachment_path);
            }
            DB::table('communication_stories')->where('id', $story->id)->delete();
            $count++;
        }
    });
    $this->info("Removed {$count} expired stories.");
})->purpose('Remove expired Communication stories and their private attachments');

Schedule::command('communication:prune-stories')->hourly();
