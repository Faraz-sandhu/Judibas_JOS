<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::table('canteen_settings',fn(Blueprint $t)=>$t->string('timezone',100)->default('Asia/Karachi'));}public function down():void {Schema::table('canteen_settings',fn(Blueprint $t)=>$t->dropColumn('timezone'));}};
