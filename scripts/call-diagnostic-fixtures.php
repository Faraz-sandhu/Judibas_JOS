<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
abort_unless(app()->environment(['local', 'testing']), 403);
$path = base_path('.preview/call-diagnostic.json');
if (($argv[1] ?? '') === 'cleanup') {
    if (! file_exists($path)) exit;
    $data = json_decode(file_get_contents($path), true);
    foreach ($data['users'] as $user) {
        $row = App\Models\User::find($user['id']);
        if ($row && ! preg_match('/^call-diagnostic-[ab]-[a-f0-9]+@example\.test$/D', $row->email)) throw new RuntimeException('Refusing to remove a non-diagnostic user.');
    }
    Illuminate\Support\Facades\DB::transaction(function () use ($data) {
        $db = Illuminate\Support\Facades\DB::class;
        $ids = array_column($data['users'], 'id');
        $db::table('communication_calls')->where('conversation_id', $data['conversation'])->delete();
        $db::table('communication_conversations')->where('id', $data['conversation'])->delete();
        $db::table('sessions')->whereIn('user_id', $ids)->delete();
        if (isset($data['session_id'])) $db::table('sessions')->where('id', $data['session_id'])->delete();
        App\Models\User::whereIn('id', $ids)->delete();
    });
    unlink($path);
    echo "Temporary call diagnostic records removed.\n";
    exit;
}
if (file_exists($path)) throw new RuntimeException('Clean up the previous call diagnostic first.');
$super = in_array('--super', $argv, true);
$data = Illuminate\Support\Facades\DB::transaction(function () use ($super) {
    $db = Illuminate\Support\Facades\DB::class;
    $company = $db::table('communication_companies')->orderBy('id')->value('id');
    $tag = bin2hex(random_bytes(6));$password = bin2hex(random_bytes(20));$users = [];
    foreach (($super ? ['a'] : ['a', 'b']) as $kind) {
        $user = App\Models\User::factory()->create(['name' => 'Call diagnostic '.$kind, 'email' => 'call-diagnostic-'.$kind.'-'.$tag.'@example.test', 'password' => $password, 'company_id' => $company, 'is_active' => true]);
        $db::table('user_product_access')->insert(['user_id' => $user->id, 'product_slug' => 'communication']);
        $users[] = ['id' => $user->id, 'email' => $user->email];
    }
    $ids = array_column($users, 'id');sort($ids);
    $chat = $db::table('communication_conversations')->insertGetId(['super_participant' => $super, 'kind' => 'direct', 'direct_key' => ($super ? 'super:' : '').implode(':', $ids), 'created_by' => $ids[0], 'created_at' => now(), 'updated_at' => now()]);
    foreach ($ids as $id) $db::table('communication_members')->insert(['conversation_id' => $chat, 'user_id' => $id]);

    return ['users' => $users, 'password' => $password, 'conversation' => $chat];
});
if ($super) {
    $session = app('session')->driver();$session->start();$session->put('judibas_admin', true);$session->save();
    $data['session_id'] = $session->getId();
    $data['session_cookie_name'] = config('session.cookie');
    $data['session_cookie'] = app('encrypter')->encrypt(Illuminate\Cookie\CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false);
}
file_put_contents($path, json_encode($data));
echo "Temporary call diagnostic records ready.\n";
