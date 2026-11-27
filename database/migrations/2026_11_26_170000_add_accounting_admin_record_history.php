<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('accounting_admin_record_history',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('company_id');$t->string('record_type',30);$t->unsignedBigInteger('record_id');$t->string('action',20);$t->unsignedBigInteger('actor_id')->nullable();$t->text('reason');$t->jsonb('snapshot');$t->timestamp('created_at');$t->index(['company_id','record_type','record_id']);
  });
  Schema::table('accounting_signed_copies',fn(Blueprint $t)=>$t->timestamp('removed_at')->nullable());
 }
 public function down(): void {Schema::table('accounting_signed_copies',fn(Blueprint $t)=>$t->dropColumn('removed_at'));Schema::dropIfExists('accounting_admin_record_history');}
};
