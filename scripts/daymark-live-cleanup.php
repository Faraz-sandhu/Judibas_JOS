<?php
// CLI-only cleanup for check-daymark-live.mjs; never a web endpoint.
if (PHP_SAPI !== 'cli' || count($argv) !== 4) { exit(1); }
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local')) { exit(1); }
[$script,$owner,$date,$marker]=$argv;
if (!preg_match('/^Daymark websocket verification \d+$/',$marker)) { exit(1); }
Illuminate\Support\Facades\DB::transaction(function()use($owner,$date,$marker){
 $report=App\Modules\Daymark\Models\Report::where('user_id',$owner)->where('date',$date)->lockForUpdate()->first();
 if(!$report||$report->completed!==$marker||$report->user->email!=='daymark.employee1.alpha@example.test')return;
 $name=$report->user->name;$reviewer=$report->reviewer_id;$report->delete();
 Illuminate\Support\Facades\DB::table('daymark_audits')->where('actor_id',$owner)->where('target',$date)->delete();
 if($reviewer)Illuminate\Support\Facades\DB::table('daymark_audits')->where('actor_id',$reviewer)->where('target',$name.' / '.$date)->delete();
});
