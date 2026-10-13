<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  foreach(DB::table('pms_tasks')->whereNull('department_id')->whereNotNull('project_id')->get() as $task){
   $departments=DB::table('pms_department_project')->where('project_id',$task->project_id)->pluck('department_id');
   $workflowDepartment=$task->workflow_id?DB::table('pms_workflows')->where('id',$task->workflow_id)->value('department_id'):null;
   $id=$workflowDepartment&&$departments->contains($workflowDepartment)?$workflowDepartment:($departments->count()===1?$departments->first():null);
   if($id)DB::table('pms_tasks')->where('id',$task->id)->update(['department_id'=>$id]);
  }
  foreach(DB::table('pms_sprints')->whereNull('department_id')->get() as $sprint){
   $tasks=DB::table('pms_tasks')->where('sprint_id',$sprint->id)->get();$ids=$tasks->pluck('department_id')->unique();
   if($ids->contains(null))continue;
   if($tasks->isEmpty()){$ids=DB::table('pms_department_project')->where('project_id',$sprint->project_id)->pluck('department_id');if($ids->count()!==1)continue;}
   foreach($ids->values() as $index=>$department){
    if($index===0){DB::table('pms_sprints')->where('id',$sprint->id)->update(['department_id'=>$department]);continue;}
    $copy=(array)$sprint;unset($copy['id']);$copy['department_id']=$department;$id=DB::table('pms_sprints')->insertGetId($copy);
    DB::table('pms_tasks')->where('sprint_id',$sprint->id)->where('department_id',$department)->update(['sprint_id'=>$id]);
   }
  }
 }
 // Keep inferred department assignments and split sprints on rollback to preserve work.
 public function down():void {}
};
