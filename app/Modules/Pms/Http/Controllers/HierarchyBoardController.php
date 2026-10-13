<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Models\{Client,Company,Department,Projects,Sprint,Tasks};
use App\Modules\Pms\Services\{PmsAccess,PmsVisibility};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class HierarchyBoardController extends Controller {
 public function index(Request $r){
  PmsAccess::requirePermission($r,'project-view');PmsVisibility::syncCompanies();
  $v=$r->validate(['scope'=>['required',Rule::in(['company','client','project'])],'id'=>'required|integer|min:1','project'=>'nullable|integer','department'=>'nullable|integer','sprint'=>'nullable|string']);
  $projects=Projects::where('is_general',false);
  if($v['scope']==='company'){$owner=Company::whereIn('id',PmsVisibility::companyIds())->findOrFail($v['id']);$projects->where('company_id',$owner->id);}
  elseif($v['scope']==='client'){$owner=Client::whereIn('company_id',PmsVisibility::companyIds())->findOrFail($v['id']);$projects->where('client_id',$owner->id);}
  else {$owner=Projects::findOrFail($v['id']);$projects->whereKey($owner->id);}
  $projects=$projects->orderBy('name')->get(['id','name','client_id','company_id']);
  if(!PmsVisibility::allProjects())abort_if($projects->isEmpty(),404);
  $projectIds=$projects->pluck('id');$selectedProject=$v['project']??null;
  if($selectedProject)abort_unless($projectIds->contains($selectedProject),422,'The project does not belong to this board.');
  $visibleTasks=Tasks::whereIn('project_id',$projectIds)->when($selectedProject,fn($q)=>$q->where('project_id',$selectedProject));
  $departments=Department::whereHas('projects',fn($q)=>$q->whereIn('pms_projects.id',$selectedProject?[$selectedProject]:$projectIds));
  if(!PmsVisibility::allProjects())$departments->whereIn('id',(clone $visibleTasks)->select('department_id')->distinct());
  $departments=$departments->orderBy('dept_name')->get(['id','dept_name']);$departmentId=$v['department']??null;
  if($departmentId)abort_unless($departments->contains('id',$departmentId),422,'The department does not belong to this board.');
  $visibleTasks->when($departmentId,fn($q)=>$q->where('department_id',$departmentId));
  $sprints=Sprint::whereIn('project_id',$selectedProject?[$selectedProject]:$projectIds)->when($departmentId,fn($q)=>$q->where('department_id',$departmentId))->with(['project:id,name','department:id,dept_name'])->withCount('tasks')->orderByDesc('id')->get();
  $sprint=$v['sprint']??'all';$selectedSprint=null;
  if($sprint==='backlog')$visibleTasks->whereNull('sprint_id');
  elseif($sprint!=='all'){$selectedSprint=$sprints->firstWhere('id',(int)$sprint);abort_unless($selectedSprint,422,'The sprint does not belong to this board.');$visibleTasks->where('sprint_id',$selectedSprint->id);}
  $tasks=$visibleTasks->with(['project:id,name,client_id','project.client:id,name','department:id,dept_name','sprint:id,name,project_id,department_id','workflow.columns','users:id,name,profile_img','subtasks:id,task_id,status','timerLogs:id,task_id,user_id,start_time,end_time','incomingHandoff.sourceDepartment:id,dept_name','outgoingHandoffs.destinationDepartment:id,dept_name'])->withCount(['comments','attachments'])->orderBy('position')->orderBy('id')->get();
  return response()->json(['data'=>['scope'=>$v['scope'],'owner'=>['id'=>$owner->id,'name'=>$owner->name],'projects'=>$projects,'departments'=>$departments,'sprints'=>$sprints,'selectedSprint'=>$selectedSprint,'tasks'=>$tasks,'stats'=>['projects'=>$projects->count(),'tasks'=>$tasks->count(),'sprints'=>$sprints->count(),'backlog'=>$tasks->whereNull('sprint_id')->count()]]]);
 }
 public function move(Request $r,Tasks $task){
  PmsAccess::requirePermission($r,'task-status');$v=$r->validate(['status'=>['required',Rule::in(['pending','in_progress','in_review','completed'])]]);
  $task->load('workflow.columns');$payload=['status'=>$v['status']];
  if($task->workflow){$column=$task->workflow->columns->first(function($c)use($v){$status=$c->is_completed?'completed':($c->is_initial?'pending':(str_contains(strtolower($c->name),'review')?'in_review':'in_progress'));return $status===$v['status'];});abort_unless($column,422,'This workflow has no matching stage. Open the department board to choose a stage.');$payload=['workflow_id'=>$task->workflow_id,'workflow_column_id'=>$column->id];}
  $r->replace($payload);return app(TaskController::class)->boardUpdate($r,$task);
 }
}
