<?php
namespace App\Modules\Pms\Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Modules\Pms\Models\{Client,Company,Department,Projects,Sprint,Tasks,Workflow};
use App\Modules\Pms\Services\PmsVisibility;
/** Optional sample data. Uses shared companies; never creates company records in Administration. */
class PmsClientSampleSeeder extends Seeder {
 private function pivot(string $table,array $key):void {if(!DB::table($table)->where($key)->exists())DB::table($table)->insert($key+['created_at'=>now(),'updated_at'=>now()]);}
 public function run():void {
  DB::transaction(function(){
   PmsVisibility::syncCompanies();
   foreach(['Engineering'=>'Development','Creative Design'=>'Graphic'] as $old=>$name){$legacy=Department::where('dept_name',$old)->first();if($legacy&&!Department::where('dept_name',$name)->exists())$legacy->update(['dept_name'=>$name]);}
   $departments=[];
   foreach(['Admin','Content Writer','Development','Ecommerce','Graphic','Management','Marketing','Project Manager','SEO'] as $name)$departments[$name]=Department::firstOrCreate(['dept_name'=>$name],['description'=>$name.' department','status'=>1]);
   $workflows=[];
   foreach(['Development','Graphic','SEO','Marketing'] as $name){
    $workflow=Workflow::where('department_id',$departments[$name]->id)->where('is_active',true)->first();
    if(!$workflow)$workflow=Workflow::create(['name'=>$name.' Delivery','department_id'=>$departments[$name]->id,'description'=>'Delivery stages for '.$name.' work.','is_active'=>true,'is_default'=>false,'transition_mode'=>'any']);
    if(!$workflow->columns()->exists())foreach(['To Do','In Progress','Review','Completed'] as $position=>$column)$workflow->columns()->create(['name'=>$column,'position'=>$position,'color'=>['secondary','primary','warning','success'][$position],'is_initial'=>$position===0,'is_completed'=>$position===3]);
    $workflows[$name]=$workflow->load('columns');
   }
   $definitions=[
    'Judibass'=>['client'=>'Cedar Retail','project'=>'Cedar Customer Portal','sprint'=>'Customer Portal Delivery'],
    'Skyinfinit'=>['client'=>'Northstar Foods','project'=>'Northstar Online Store','sprint'=>'Online Store Launch'],
    'Cruxlo'=>['client'=>'Atlas Logistics','project'=>'Atlas Shipment Tracking','sprint'=>'Shipment Tracking Release'],
   ];
   $tasks=[
    'Development'=>['Build the customer account dashboard','Implement secure account registration'],
    'Graphic'=>['Prepare responsive interface designs','Create the project visual identity'],
    'SEO'=>['Audit technical search performance','Research service and product keywords'],
    'Marketing'=>['Prepare the launch campaign plan','Create social media launch content'],
   ];
   $eligible=DB::table('communication_companies')->orderBy('id')->get();
   foreach($eligible as $platform){
    $definition=$definitions[$platform->name]??['client'=>$platform->name.' Retail Client','project'=>$platform->name.' Customer Website','sprint'=>'Website Launch'];
    $company=Company::where('platform_company_id',$platform->id)->firstOrFail();
    $client=Client::firstOrCreate(['company_id'=>$company->id,'name'=>$definition['client']],['email'=>'contact@'.strtolower(str_replace(' ','',$definition['client'])).'.example','description'=>'Sample client for checking company, project, and department visibility.','status'=>true]);
    $project=Projects::withoutGlobalScopes()->firstOrCreate(['name_key'=>Projects::normalizeName($definition['project'])],['name'=>$definition['project'],'client_id'=>$client->id,'company_id'=>$company->id,'description'=>'Client delivery across Development, Graphic, SEO, and Marketing.','start_date'=>today()->toDateString(),'end_date'=>today()->addWeeks(3)->toDateString()]);
    // Attach the pre-existing sample portal to the shared company without resetting its task data.
    $project->forceFill(['company_id'=>$company->id,'client_id'=>$client->id,'approval'=>'approved'])->save();
    $project->departments()->syncWithoutDetaching(collect(['Development','Graphic','SEO','Marketing'])->map(fn($n)=>$departments[$n]->id)->all());
    $project->workflows()->syncWithoutDetaching(collect($workflows)->pluck('id')->all());
    $memberIds=DB::table('users')->where('is_active',true)->where('status',1)->whereIn('id',DB::table('user_product_access')->where('product_slug','projects')->select('user_id'))->where(fn($q)=>$q->where('company_id',$platform->id)->orWhereIn('id',DB::table('communication_company_memberships')->where('company_id',$platform->id)->select('user_id')))->orderBy('id')->pluck('id');
    foreach($tasks as $name=>$titles){
     $workflow=$workflows[$name];$department=$departments[$name];
     $sprint=Sprint::withoutGlobalScopes()->firstOrCreate(['project_id'=>$project->id,'department_id'=>$department->id,'name'=>$definition['sprint'].' - '.$name],['goal'=>'Deliver the first '.$name.' milestone.','status'=>'active','start_date'=>today()->toDateString(),'end_date'=>today()->addWeek()->toDateString()]);
     foreach($titles as $index=>$title){
      $column=$workflow->columns[$index===0?1:0];
      $task=Tasks::withoutGlobalScopes()->firstOrCreate(['project_id'=>$project->id,'department_id'=>$department->id,'title'=>$title],['description'=>'Review the requirements, complete the deliverable, and share the result for review.','workflow_id'=>$workflow->id,'workflow_column_id'=>$column->id,'sprint_id'=>$index===0?$sprint->id:null,'status'=>$index===0?'in_progress':'pending','priority'=>1,'position'=>$index,'due_date'=>today()->addDays(5),'original_estimate_minutes'=>120,'created_by'=>'Super Admin']);
      $subtask=$task->subtasks()->firstOrCreate(['title'=>$index===0?'Confirm the acceptance criteria':'Review the implementation checklist'],['status'=>'pending','priority'=>1,'created_by'=>'Super Admin']);
      if($memberIds->isNotEmpty()){$member=$memberIds[($index+array_search($name,array_keys($tasks)))%$memberIds->count()];$this->pivot('pms_project_user',['project_id'=>$project->id,'user_id'=>$member]);$this->pivot('pms_task_user',['task_id'=>$task->id,'user_id'=>$member]);$this->pivot('pms_subtask_user',['subtask_id'=>$subtask->id,'user_id'=>$member]);}
     }
    }
    // Keep the earlier brand-refresh sample visible under its proper client.
    if($platform->name==='Skyinfinit'){$previous=Projects::withoutGlobalScopes()->where('name_key','northstar brand refresh')->whereNull('client_id')->first();if($previous)$previous->forceFill(['company_id'=>$company->id,'client_id'=>$client->id])->save();}
   }
  });
  $this->command?->info('Sample clients, projects, department sprints, backlog tasks and subtasks are ready. Shared companies and existing accounts were preserved.');
 }
}
