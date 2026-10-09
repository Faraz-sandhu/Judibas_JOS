<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::create('communication_company_memberships',function(Blueprint $t){$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('company_id')->constrained('communication_companies')->cascadeOnDelete();$t->unique(['user_id','company_id']);$t->index(['company_id','user_id']);});
  Schema::table('communication_invitations',fn(Blueprint $t)=>$t->json('company_ids')->nullable());
  DB::table('users')->whereNotNull('company_id')->orderBy('id')->chunkById(500,function($users){DB::table('communication_company_memberships')->insertOrIgnore($users->map(fn($u)=>['user_id'=>$u->id,'company_id'=>$u->company_id])->all());});
 }
 public function down(): void {Schema::table('communication_invitations',fn(Blueprint $t)=>$t->dropColumn('company_ids'));Schema::dropIfExists('communication_company_memberships');}
};
