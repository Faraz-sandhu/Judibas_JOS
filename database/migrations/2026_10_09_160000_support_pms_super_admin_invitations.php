<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('pms_invitations',fn(Blueprint $t)=>$t->unsignedBigInteger('sender_id')->nullable()->change());if(!Schema::hasColumn('users','last_seen_at'))Schema::table('users',fn(Blueprint $t)=>$t->timestamp('last_seen_at')->nullable());}public function down():void{Schema::table('pms_invitations',fn(Blueprint $t)=>$t->unsignedBigInteger('sender_id')->nullable(false)->change());}};
