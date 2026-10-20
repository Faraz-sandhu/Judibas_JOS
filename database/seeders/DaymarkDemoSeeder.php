<?php
namespace Database\Seeders;
use App\Models\User;
use App\Modules\Daymark\Models\Report;
use App\Services\UserProductAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class DaymarkDemoSeeder extends Seeder {
 public function run():void {
  if(!app()->environment(['local','testing']))throw new \RuntimeException('Daymark demo data is only available locally or in tests.');
  DB::transaction(function(){
   foreach(['alpha'=>'Alpha','beta'=>'Beta'] as $slug=>$label){
    $company=DB::table('communication_companies')->where('name','[DEMO] Daymark '.$label)->value('id');
    if(!$company)$company=DB::table('communication_companies')->insertGetId(['name'=>'[DEMO] Daymark '.$label,'created_at'=>now(),'updated_at'=>now()]);
    $departments=[];foreach(['Engineering','Design','Operations'] as $name){DB::table('daymark_departments')->updateOrInsert(['company_id'=>$company,'name'=>$name],['updated_at'=>now(),'created_at'=>now()]);$departments[$name]=DB::table('daymark_departments')->where('company_id',$company)->where('name',$name)->value('id');}
    $members=[];
    foreach(['admin'=>'company_admin','pm'=>'pm','om'=>'om','hr'=>'hr','employee1'=>'employee','employee2'=>'employee','inactive'=>'employee'] as $key=>$role){
     $email='daymark.'.$key.'.'.$slug.'@example.test';$user=User::where('email',$email)->first();
     if(!$user){$user=new User;$user->forceFill(['name'=>$label.' '.ucfirst($key).' (Daymark demo)','email'=>$email,'password'=>'DaymarkDemo2026!','is_active'=>$key!=='inactive','status'=>$key==='inactive'?2:1]);$user->save();UserProductAccess::sync($user,['product_slugs'=>['daymark'],'company_ids'=>[$company],'daymark_company_id'=>$company,'daymark_role'=>$role,'daymark_department_id'=>$departments[$key==='employee2'?'Design':'Engineering'],'daymark_designation'=>$role==='employee'?'Specialist':'Reporting lead']);}
     $members[$key]=$user;
    }
    foreach(['pm','om'] as $manager)foreach(['employee1','employee2'] as $person)DB::table('daymark_assignments')->updateOrInsert(['manager_id'=>$members[$manager]->id,'employee_id'=>$members[$person]->id],[]);
    for($day=0;$day<14;$day++){$date=today()->subDays($day);if($date->isWeekend())continue;
     foreach(['employee1','employee2','pm','om','hr'] as $index=>$key){$status=['submitted','reviewed','changes_requested','draft'][($day+$index)%4];
      Report::firstOrCreate(['user_id'=>$members[$key]->id,'date'=>$date->toDateString()],['completed'=>'<p>Demo: completed the '.$label.' delivery checklist and reviewed team updates.</p>','progress'=>'<p>Demo: testing the next release and documenting results.</p>','plan'=>'<ul><li>Review outstanding feedback</li><li>Share the next progress update</li></ul>','blockers'=>($day+$index)%5===0?'<p>Waiting for demo design approval.</p>':'<p>None</p>','content_format'=>'html','hours'=>6.5+($index%3)*.5,'status'=>$status,'feedback'=>$status==='changes_requested'?'Demo feedback: include the outcome and next steps.':($status==='reviewed'?'Demo feedback: reviewed and approved.':null),'reviewer_id'=>in_array($status,['reviewed','changes_requested'])?$members['admin']->id:null]);
     }
    }
   }
  });
  $this->command?->info('Daymark demo data is ready. Demo account password: DaymarkDemo2026! No invitations or emails were sent.');
 }
}
