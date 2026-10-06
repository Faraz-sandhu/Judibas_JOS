<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = base_path('.preview/communication-test-resources.json');
if (! file_exists($path)) {
    exit(0);
}$r = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
foreach ($r['users'] as $id) {
    $u = User::find($id);
    if ($u && ! preg_match('/^comm-(a|b|manager|outsider|invite)-[0-9]+@example\\.test$/', $u->email)) {
        throw new RuntimeException('Refusing to remove a non-test employee.');
    }
}
$paths = DB::table('communication_messages')->whereIn('conversation_id', $r['conversations'])->whereNotNull('attachment_path')->pluck('attachment_path')->all();
$paths = array_merge($paths, DB::table('communication_stories')->whereIn('id', $r['stories'] ?? [])->whereIn('title', ['Browser story', 'Status'])->whereNotNull('attachment_path')->pluck('attachment_path')->all());
$paths = array_merge($paths, User::whereIn('id', $r['users'])->whereNotNull('profile_photo_path')->pluck('profile_photo_path')->all());
DB::transaction(function () use ($r) {
    DB::table('communication_audits')->where(function ($q) use ($r) {
        $q->whereIn('viewed_user_id', $r['users'])->orWhereIn('conversation_id', $r['conversations']);
    })->delete();
    DB::table('communication_invitations')->whereIn('id', $r['invitations'] ?? [])->delete();
    DB::table('communication_conversations')->whereIn('id', $r['conversations'])->delete();
    DB::table('communication_teams')->whereIn('id', $r['teams'] ?? [])->where('name', 'Browser Test Team')->delete();
    DB::table('communication_stories')->whereIn('id', $r['stories'] ?? [])->whereIn('title', ['Browser story', 'Status'])->delete();
    User::whereIn('id', $r['users'])->delete();
    DB::table('communication_companies')->whereIn('id', $r['companies'] ?? [])->delete();
});
Storage::disk('local')->delete($paths);
unlink($path);
echo "Temporary Communication check records removed.\n";
