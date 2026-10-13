<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Models\{Projects,Tasks,Sprint,User};
use App\Modules\Pms\Services\{PmsAccess,PmsVisibility,PmsAuth};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class TaskTransferController extends Controller {
 public function options(Tasks $task){PmsAccess::requirePermission(request(),'task-transfer-company');return response()->json(['task'=>['id'=>$task->id,'title'=>$task->title],'projects'=>Projects::where('is_general',false)->where('approval','approved')->whereNotNull('client_id')->with(['company:id,name','client:id,name','departments:id,dept_name','workflows.columns','sprints'])->orderBy('name')->get()]);}
 private function snapshot(Tasks $task):array {$task->load(['project.company','project.client','department','users:id,name','subtasks'=>fn($q)=>$q->withoutGlobalScope('visibility')->with('users:id,name')]);return ['task_id'=>$task->id,'title'=>$task->title,'project_id'=>$task->project_id,'project'=>$task->project?->name,'company_id'=>$task->project?->company_id,'company'=>$task->project?->company?->name,'client'=>$task->project?->client?->name,'department_id'=>$task->department_id,'department'=>$task->department?->dept_name,'workflow_id'=>$task->workflow_id,'workflow_column_id'=>$task->workflow_column_id,'sprint_id'=>$task->sprint_id,'status'=>$task->status,'assignees'=>$task->users->map(fn($u)=>['id'=>$u->id,'name'=>$u->name])->all(),'subtasks'=>$task->subtasks->map(fn($s)=>['id'=>$s->id,'assignees'=>$s->users->pluck('id')->all()])->all()];}
 public function store(Request $r,Tasks $task){
  PmsAccess::requirePermission($r,'task-transfer-company');
  $v=$r->validate(['project_id'=>'required|integer|exists:pms_projects,id','department_id'=>'required|integer|exists:pms_departments,id','sprint_id'=>'nullable|integer|exists:pms_sprints,id','reason'=>'nullable|string|max:2000']);
  $project=Projects::where('approval','approved')->with('workflows.columns')->findOrFail($v['project_id']);PmsVisibility::department($project->id,$v['department_id']);
  abort_if($task->project_id===$project->id&&(int)$task->department_id===(int)$v['department_id'],422,'Select a different project or department.');
  if(!empty($v['sprint_id']))abort_unless($project->sprints()->where('department_id',$v['department_id'])->whereKey($v['sprint_id'])->exists(),422,'Select a sprint from the destination department.');
  $workflow=$project->workflows->first(fn($w)=>$w->is_active&&(int)$w->department_id===(int)$v['department_id'])??$project->workflows->first(fn($w)=>$w->is_active&&$w->department_id===null);
  abort_unless($workflow,422,'The destination department needs an active workflow.');
  $column=$workflow->columns->first(fn($c)=>($c->is_completed?'completed':($c->is_initial?'pending':(str_contains(strtolower($c->name),'review')?'in_review':'in_progress')))===$task->status);abort_unless($column,422,'The destination workflow needs a stage matching the task status.');
  $result=DB::transaction(function()use($task,$project,$v,$workflow,$column){
   $task=Tasks::whereKey($task->id)->lockForUpdate()->firstOrFail();abort_if(DB::table('pms_timer_logs')->where(fn($q)=>$q->where('task_id',$task->id)->orWhereIn('subtask_id',DB::table('pms_subtasks')->where('task_id',$task->id)->select('id')))->whereNull('end_time')->whereNotNull('start_time')->exists(),422,'Pause or stop running task and subtask timers before transferring.');
   $before=$this->snapshot($task);$userQuery=User::query()->where('is_active',true)->where('status',1);PmsVisibility::assignees($userQuery,$project->company_id);$eligible=$userQuery->pluck('id');
   $removed=$task->users->whereNotIn('id',$eligible)->pluck('id')->all();$task->users()->detach($removed);
   foreach($task->subtasks as $subtask)$subtask->users()->detach($subtask->users->whereNotIn('id',$eligible)->pluck('id')->all());
   $task->update(['project_id'=>$project->id,'department_id'=>$v['department_id'],'sprint_id'=>$v['sprint_id']??null,'workflow_id'=>$workflow->id,'workflow_column_id'=>$column->id,'position'=>(Tasks::where('project_id',$project->id)->where('department_id',$v['department_id'])->max('position')??0)+1]);
   $project->users()->syncWithoutDetaching($task->users()->pluck('users.id')->all());$after=$this->snapshot(Tasks::withoutGlobalScope('visibility')->findOrFail($task->id));
   DB::table('pms_task_transfers')->insert(['task_id'=>$task->id,'user_id'=>PmsAuth::id(),'actor_name'=>PmsAuth::user()->name,'before'=>json_encode($before),'after'=>json_encode($after),'reason'=>$v['reason']??null,'created_at'=>now(),'updated_at'=>now()]);
   return ['task'=>Tasks::withoutGlobalScope('visibility')->findOrFail($task->id)->load('users:id,name,profile_img'),'removed_assignee_ids'=>$removed];
  });
  return response()->json($result+['message'=>'Task transferred. Assign employees in the destination company as needed.','redirect_url'=>\App\Modules\Pms\Services\PmsUrls::workspace(['section'=>'project-board','project'=>$project->id,'department'=>$v['department_id'],'sprint'=>$v['sprint_id']??'backlog','issue'=>$task->id])]);
 }
}
