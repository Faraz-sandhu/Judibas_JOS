<?php
namespace App\Modules\Pms\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
class PmsVisibility {
 public static function unrestricted():bool {if(!request()->hasSession()||(!\Illuminate\Support\Facades\Auth::id()&&!request()->session()->get('judibas_admin')))return true;return \App\Services\PlatformAccess::administrator(request())||PmsAuth::user()?->hasRole('admin');}
 public static function allProjects():bool {return self::unrestricted()||PmsAccess::allows(request(),'project-view-all');}
 public static function companyIds():array {
  if(request()->attributes->has('pms.company_ids'))return request()->attributes->get('pms.company_ids');
  if(self::allProjects())$ids=\App\Modules\Pms\Models\Company::whereNotNull('platform_company_id')->pluck('id')->all();
  else {$id=PmsAuth::id();$platform=DB::table('communication_company_memberships')->where('user_id',$id)->pluck('company_id')->all();$primary=request()->user()?->company_id;if($primary)$platform[]=$primary;$ids=\App\Modules\Pms\Models\Company::whereIn('platform_company_id',$platform)->pluck('id')->all();}
  request()->attributes->set('pms.company_ids',$ids);return $ids;
 }
 public static function teamTasks():bool {return !self::allProjects()&&PmsAccess::allows(request(),'task-view-team');}
 public static function teamDepartmentIds():array {if(!self::teamTasks())return [];return DB::table('pms_user_departments')->where('user_id',PmsAuth::id())->pluck('dept_id')->all();}
 public static function assignedTaskIds(){return DB::table('pms_tasks')->where(function($t){$t->whereIn('id',DB::table('pms_task_user')->where('user_id',PmsAuth::id())->select('task_id'))->orWhereIn('id',DB::table('pms_subtasks')->join('pms_subtask_user','pms_subtask_user.subtask_id','=','pms_subtasks.id')->where('pms_subtask_user.user_id',PmsAuth::id())->select('pms_subtasks.task_id'));if(self::teamTasks())$t->orWhereIn('department_id',self::teamDepartmentIds());})->select('id');}
 public static function projects(Builder $q):void {
  if(self::unrestricted())return;
  $q->whereIn('pms_projects.company_id',self::companyIds());
  if(!self::allProjects())$q->whereHas('tasks');
 }
 public static function tasks(Builder $q):void {
  if(self::unrestricted())return;
  $q->where(fn(Builder $t)=>$t->whereIn('pms_tasks.project_id',DB::table('pms_projects')->whereIn('company_id',self::companyIds())->select('id'))->orWhereNull('pms_tasks.project_id'));
  if(!self::allProjects())$q->whereIn('pms_tasks.id',self::assignedTaskIds());
 }
 public static function subtasks(Builder $q):void {
  if(self::unrestricted())return;
  $parents=DB::table('pms_tasks')->where(fn($t)=>$t->whereIn('project_id',DB::table('pms_projects')->whereIn('company_id',self::companyIds())->select('id'))->orWhereNull('project_id'))->select('id');
  $q->whereIn('pms_subtasks.task_id',$parents);
  if(!self::allProjects())$q->where(fn($s)=>$s->whereIn('pms_subtasks.task_id',self::assignedTaskIds())->orWhereHas('users',fn($u)=>$u->where('users.id',PmsAuth::id())));
 }
 public static function sprints(Builder $q):void {
  if(self::allProjects())return;
  if(self::teamTasks()){$q->whereHas('project')->where(fn($s)=>$s->whereIn('department_id',self::teamDepartmentIds())->orWhereHas('tasks'));return;}
  $q->whereHas('project')->whereExists(function($tasks){$tasks->selectRaw('1')->from('pms_tasks')->whereColumn('pms_tasks.project_id','pms_sprints.project_id')->whereRaw('(pms_tasks.department_id = pms_sprints.department_id OR (pms_tasks.department_id IS NULL AND pms_sprints.department_id IS NULL))')->where(fn($t)=>$t->whereIn('pms_tasks.id',DB::table('pms_task_user')->where('user_id',PmsAuth::id())->select('task_id'))->orWhereIn('pms_tasks.id',DB::table('pms_subtasks')->join('pms_subtask_user','pms_subtask_user.subtask_id','=','pms_subtasks.id')->where('pms_subtask_user.user_id',PmsAuth::id())->select('pms_subtasks.task_id')));});
 }
 public static function syncCompanies():void {
  $changed=false;
  foreach(DB::table('communication_companies')->whereNotIn('id',DB::table('pms_companies')->whereNotNull('platform_company_id')->select('platform_company_id'))->get() as $c){
   $legacy=DB::table('pms_companies')->whereNull('platform_company_id')->whereRaw('LOWER(name) = ?',[mb_strtolower($c->name)])->first();
   if($legacy)DB::table('pms_companies')->where('id',$legacy->id)->update(['platform_company_id'=>$c->id,'name'=>$c->name,'updated_at'=>now()]);
   else DB::table('pms_companies')->insertOrIgnore(['name'=>$c->name,'platform_company_id'=>$c->id,'status'=>true,'created_at'=>now(),'updated_at'=>now()]);
   $changed=true;
  }
  foreach(DB::table('pms_companies')->join('communication_companies','communication_companies.id','=','pms_companies.platform_company_id')->whereColumn('pms_companies.name','<>','communication_companies.name')->get(['pms_companies.id','communication_companies.name']) as $c){DB::table('pms_companies')->where('id',$c->id)->update(['name'=>$c->name,'updated_at'=>now()]);$changed=true;}
  if($changed)request()->attributes->remove('pms.company_ids');
 }
 public static function assignees(Builder $q,?int $companyId):void {if(!$companyId)return;$platform=DB::table('pms_companies')->where('id',$companyId)->value('platform_company_id');if(!$platform){$q->whereRaw('1=0');return;}$q->where(fn(Builder $u)=>$u->where('users.company_id',$platform)->orWhereIn('users.id',DB::table('communication_company_memberships')->where('company_id',$platform)->select('user_id'))->orWhere('users.judibas_administrator',true)->orWhereHas('roles',fn($r)=>$r->where('role_key','admin')));}
 public static function department(int $projectId,int $departmentId):void {abort_unless(DB::table('pms_department_project')->where('project_id',$projectId)->where('department_id',$departmentId)->exists(),422,'Select a department linked to this project.');}
}
