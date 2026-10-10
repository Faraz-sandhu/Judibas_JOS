<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('pms_sub_task_pending_summaries',fn(Blueprint $table)=>$table->string('invest_time')->nullable());}
 public function down():void {Schema::table('pms_sub_task_pending_summaries',fn(Blueprint $table)=>$table->dropColumn('invest_time'));}
};
