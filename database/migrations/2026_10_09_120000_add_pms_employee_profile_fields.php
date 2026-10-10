<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('users',function(Blueprint $t){$t->string('surname')->nullable();$t->string('designation')->nullable();$t->smallInteger('status')->default(1);$t->string('profile_img')->nullable();});}
 public function down():void {Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['surname','designation','status','profile_img']));}
};
