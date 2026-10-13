<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  Schema::table('pms_companies',fn(Blueprint $t)=>$t->foreignId('platform_company_id')->nullable()->unique()->constrained('communication_companies')->restrictOnDelete());
  foreach(DB::table('communication_companies')->get() as $company){
   $existing=DB::table('pms_companies')->whereRaw('LOWER(name) = ?', [mb_strtolower($company->name)])->first();
   if($existing)DB::table('pms_companies')->where('id',$existing->id)->update(['platform_company_id'=>$company->id]);
   else DB::table('pms_companies')->insert(['name'=>$company->name,'platform_company_id'=>$company->id,'status'=>true,'created_at'=>now(),'updated_at'=>now()]);
  }
  Schema::create('pms_clients',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained('pms_companies')->restrictOnDelete();$t->string('name');$t->string('email')->nullable();$t->string('phone')->nullable();$t->string('website')->nullable();$t->text('description')->nullable();$t->boolean('status')->default(true);$t->timestamps();$t->unique(['company_id','name']);});
  Schema::table('pms_projects',fn(Blueprint $t)=>$t->foreignId('client_id')->nullable()->constrained('pms_clients')->restrictOnDelete());
  Schema::table('pms_sprints',fn(Blueprint $t)=>$t->foreignId('department_id')->nullable()->constrained('pms_departments')->restrictOnDelete());
  // Retain existing projects and tasks. Infer a sprint department only when all its tasks agree.
  foreach(DB::table('pms_sprints')->get() as $sprint){$ids=DB::table('pms_tasks')->where('sprint_id',$sprint->id)->distinct()->pluck('department_id');if($ids->count()===1&&$ids->first())DB::table('pms_sprints')->where('id',$sprint->id)->update(['department_id'=>$ids->first()]);}
  foreach(['client-view'=>'View clients','client-add'=>'Create clients','client-edit'=>'Edit clients','client-trash'=>'Delete clients','project-view-all'=>'View all company projects'] as $key=>$name){DB::table('pms_permissions')->insertOrIgnore(['permission_key'=>$key,'permission_name'=>$name,'created_at'=>now(),'updated_at'=>now()]);}
  $roles=DB::table('pms_roles')->whereIn('role_key',['admin','project_manager'])->pluck('id');
  foreach($roles as $role)foreach(DB::table('pms_permissions')->whereIn('permission_key',['client-view','client-add','client-edit','client-trash','project-view-all'])->pluck('id') as $id)DB::table('pms_permission_roles')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down():void {Schema::table('pms_sprints',fn(Blueprint $t)=>$t->dropConstrainedForeignId('department_id'));Schema::table('pms_projects',fn(Blueprint $t)=>$t->dropConstrainedForeignId('client_id'));Schema::dropIfExists('pms_clients');Schema::table('pms_companies',fn(Blueprint $t)=>$t->dropConstrainedForeignId('platform_company_id'));}
};
