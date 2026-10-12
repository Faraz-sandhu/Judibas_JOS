<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class UserProductAccess
{
 public static function rules(): array {return [
  'product_slugs'=>'required|array|min:1','product_slugs.*'=>['required','string','distinct',Rule::in(array_column(config('judibas.products'),'slug'))],
  'company_ids'=>'present|array|max:100','company_ids.*'=>'integer|distinct|exists:communication_companies,id',
  'communication_admin'=>'sometimes|boolean','group_ids'=>'sometimes|array|max:100','group_ids.*'=>'integer|distinct|exists:communication_conversations,id',
  'pms_task_ids'=>'sometimes|array|max:100','pms_task_ids.*'=>'integer|distinct|exists:pms_tasks,id',
  'pms_role_ids'=>'sometimes|array','pms_role_ids.*'=>'integer|distinct|exists:pms_roles,id',
  'pms_department_ids'=>'sometimes|array','pms_department_ids.*'=>'integer|distinct|exists:pms_departments,id',
 ];}
 public static function validateSelection(array $v): void {
  if(in_array('communication',$v['product_slugs'])&&!count($v['company_ids'])) throw \Illuminate\Validation\ValidationException::withMessages(['company_ids'=>'Select at least one company for Communication.']);
  if(in_array('projects',$v['product_slugs'])&&array_key_exists('pms_role_ids',$v)&&!count($v['pms_role_ids'])) throw \Illuminate\Validation\ValidationException::withMessages(['pms_role_ids'=>'Select at least one PMS role.']);
  if(count($v['pms_task_ids']??[])&&!in_array('projects',$v['product_slugs']))throw \Illuminate\Validation\ValidationException::withMessages(['pms_task_ids'=>'Task assignments require PMS access.']);
  foreach($v['group_ids']??[] as $id) if(!in_array('communication',$v['product_slugs'])||!DB::table('communication_conversations')->where('id',$id)->where('kind','group')->whereIn('company_id',$v['company_ids'])->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['group_ids'=>'Groups must belong to selected companies and require Communication access.']);
 }
 /** Called inside the caller's transaction. Product removal preserves historical work and role assignments. */
 public static function sync(User $user,array $v,bool $merge=false): void {
  $slugs=$user->judibas_administrator?array_column(config('judibas.products'),'slug'):$v['product_slugs'];
  if(!$merge) DB::table('user_product_access')->where('user_id',$user->id)->whereNotIn('product_slug',$slugs)->delete();
  foreach($slugs as $slug) DB::table('user_product_access')->updateOrInsert(['user_id'=>$user->id,'product_slug'=>$slug],[]);
  if(!$merge) DB::table('communication_company_memberships')->where('user_id',$user->id)->whereNotIn('company_id',$v['company_ids'])->delete();
  foreach($v['company_ids'] as $id) DB::table('communication_company_memberships')->updateOrInsert(['user_id'=>$user->id,'company_id'=>$id],[]);
  $user->company_id=$merge?($user->company_id??($v['company_ids'][0]??null)):($v['company_ids'][0]??null);
  if(in_array('communication',$slugs)) {
   $user->communication_admin=$merge?((bool)$user->communication_admin||(bool)($v['communication_admin']??false)):(bool)($v['communication_admin']??$user->communication_admin??false);
   foreach($v['company_ids'] as $id) CommunicationAccess::community($id);
   if(count($v['pms_task_ids']??[])&&!in_array('projects',$v['product_slugs']))throw \Illuminate\Validation\ValidationException::withMessages(['pms_task_ids'=>'Task assignments require PMS access.']);
  foreach($v['group_ids']??[] as $id) DB::table('communication_members')->updateOrInsert(['user_id'=>$user->id,'conversation_id'=>$id],[]);
  } elseif(!$merge) $user->communication_admin=false;
  $user->save();
  if(!$merge) DB::table('communication_members')->where('user_id',$user->id)->whereIn('conversation_id',DB::table('communication_conversations')->where('kind','group')->whereNotIn('company_id',CommunicationAccess::companies($user->id))->select('id'))->delete();
  if(in_array('projects',$slugs)) {
   $pms=\App\Modules\Pms\Models\User::withoutGlobalScopes()->findOrFail($user->id);
   foreach($v['pms_task_ids']??[] as $taskId){$task=\App\Modules\Pms\Models\Tasks::findOrFail($taskId);$task->users()->syncWithoutDetaching([$user->id=>['assigned_by'=>null]]);$task->project?->users()->syncWithoutDetaching([$user->id=>['assigned_by'=>null]]);}
   foreach(['pms_role_ids'=>'roles','pms_department_ids'=>'departments'] as $key=>$relation) if(array_key_exists($key,$v)) {if($merge)$pms->$relation()->syncWithoutDetaching($v[$key]);else $pms->$relation()->sync($v[$key]);}
  }
 }
}
