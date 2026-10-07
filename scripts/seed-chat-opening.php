<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=json_decode(file_get_contents(__DIR__.'/../.preview/communication-test-resources.json'),true);
$a=App\Models\User::where('email','comm-a-'.$r['suffix'].'@example.test')->firstOrFail();$b=App\Models\User::where('email','comm-b-'.$r['suffix'].'@example.test')->firstOrFail();
$direct=Illuminate\Support\Facades\DB::table('communication_conversations')->insertGetId(['kind'=>'direct','direct_key'=>implode(':',[$a->id,$b->id]),'created_at'=>now(),'updated_at'=>now()]);
foreach([$a,$b]as$u)Illuminate\Support\Facades\DB::table('communication_members')->insert(['conversation_id'=>$direct,'user_id'=>$u->id]);$r['conversations'][]=$direct;
$cases=[$direct,$r['conversations'][2],$r['conversations'][0]];
foreach($cases as$id){$kind=Illuminate\Support\Facades\DB::table('communication_conversations')->where('id',$id)->value('kind');for($i=0;$i<32;$i++){Illuminate\Support\Facades\DB::table('communication_messages')->insert(['conversation_id'=>$id,'sender_id'=>$kind==='community'?null:$a->id,'sender_name'=>$kind==='community'?'Super Admin':$a->name,'body'=>($i===30?'First unread ':($i===31?'Latest ':'History ')).$kind.' '.$i."\n".str_repeat("Message detail line\n",5),'created_at'=>$i<30?now()->subDays(3):now()->subDay(),'updated_at'=>now()]);}Illuminate\Support\Facades\DB::table('communication_members')->updateOrInsert(['conversation_id'=>$id,'user_id'=>$b->id],['read_at'=>now()->subDays(2)]);}
$r['opening_cases']=$cases;file_put_contents(__DIR__.'/../.preview/communication-test-resources.json',json_encode($r));echo "Opening-position fixtures prepared.\n";
