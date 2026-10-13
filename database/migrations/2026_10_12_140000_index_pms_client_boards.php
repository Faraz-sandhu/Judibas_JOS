<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('pms_projects',fn(Blueprint $t)=>$t->index('client_id','pms_projects_client_index'));
  Schema::table('pms_sprints',fn(Blueprint $t)=>$t->index(['project_id','department_id'],'pms_sprints_department_board_index'));
  Schema::table('pms_tasks',fn(Blueprint $t)=>$t->index(['project_id','department_id','sprint_id'],'pms_tasks_department_board_index'));
 }
 public function down():void {
  Schema::table('pms_tasks',fn(Blueprint $t)=>$t->dropIndex('pms_tasks_department_board_index'));
  Schema::table('pms_sprints',fn(Blueprint $t)=>$t->dropIndex('pms_sprints_department_board_index'));
  Schema::table('pms_projects',fn(Blueprint $t)=>$t->dropIndex('pms_projects_client_index'));
 }
};
