<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
 public function up():void {
  foreach(['task-view-team'=>'View department team tasks','task-transfer-company'=>'Transfer tasks between companies'] as $key=>$name)DB::table('pms_permissions')->insertOrIgnore(['permission_key'=>$key,'permission_name'=>$name,'created_at'=>now(),'updated_at'=>now()]);
  $permission=DB::table('pms_permissions')->where('permission_key','task-view-team')->value('id');foreach(DB::table('pms_roles')->where('role_key','team_leader')->pluck('id') as $role)DB::table('pms_permission_roles')->insertOrIgnore(['role_id'=>$role,'permission_id'=>$permission,'created_at'=>now(),'updated_at'=>now()]);
  Schema::create('pms_task_transfers',function(Blueprint $t){$t->id();$t->foreignId('task_id')->nullable()->constrained('pms_tasks')->nullOnDelete();$t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();$t->string('actor_name');$t->jsonb('before');$t->jsonb('after');$t->text('reason')->nullable();$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('pms_task_transfers');}
};
