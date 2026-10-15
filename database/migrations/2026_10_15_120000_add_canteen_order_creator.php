<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void { Schema::table('canteen_orders',function(Blueprint $t){$t->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();}); } public function down():void { Schema::table('canteen_orders',fn(Blueprint $t)=>$t->dropConstrainedForeignId('created_by')); } };
