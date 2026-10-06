<?php

use App\Models\User;
use App\Services\CommunicationAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$suffix = (string) round(microtime(true) * 1000);
$r = ['users' => [], 'companies' => [], 'teams' => [], 'conversations' => [], 'invitations' => [], 'stories' => [], 'suffix' => $suffix];
DB::transaction(function () use (&$r, $suffix) {
    foreach (['Skyinfinit Test', 'Cruxlo Test'] as $name) {
        $id = DB::table('communication_companies')->insertGetId(['name' => $name.' '.$suffix, 'created_at' => now(), 'updated_at' => now()]);
        $r['companies'][] = $id;
        $r['conversations'][] = CommunicationAccess::community($id);
    }
    foreach (['a', 'b', 'manager', 'outsider'] as $i => $kind) {
        $u = User::factory()->create(['name' => 'Communication Test '.ucfirst($kind), 'email' => 'comm-'.$kind.'-'.$suffix.'@example.test', 'password' => 'communication-test-password', 'is_active' => true, 'company_id' => $r['companies'][$i === 3 ? 1 : 0], 'communication_admin' => $kind === 'manager']);
        $r['users'][] = $u->id;
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);
    }
    $gid = DB::table('communication_conversations')->insertGetId(['kind' => 'group', 'name' => 'Browser Project Group', 'company_id' => $r['companies'][0], 'created_at' => now(), 'updated_at' => now()]);
    $r['conversations'][] = $gid;
    foreach (array_slice($r['users'], 0, 3) as $uid) {
        DB::table('communication_members')->insert(['conversation_id' => $gid, 'user_id' => $uid]);
    }
    $token = Str::random(64);
    $r['invitation_url'] = '/communication/invitations/'.$token;
    $r['invitations'][] = DB::table('communication_invitations')->insertGetId(['email' => 'comm-invite-'.$suffix.'@example.test', 'company_id' => $r['companies'][0], 'invited_by_super' => true, 'communication_admin' => false, 'group_ids' => json_encode([$gid]), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'delivery_status' => 'log', 'created_at' => now(), 'updated_at' => now()]);
});
file_put_contents(base_path('.preview/communication-test-resources.json'), json_encode($r));
echo "Temporary company/community browser fixtures prepared; no emails sent.\n";
