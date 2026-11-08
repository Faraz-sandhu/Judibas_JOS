<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('accounting_global_policy',function(Blueprint $t){$t->unsignedInteger('id')->primary();$t->boolean('approval_required')->default(true);$t->json('approval_types')->nullable();$t->string('expense_open_time',5)->default('11:00');$t->string('expense_close_time',5)->default('20:00');$t->timestamps();});
  $latest=DB::table('accounting_books')->orderByDesc('updated_at')->orderByDesc('company_id')->first();
  $values=['approval_required'=>$latest->approval_required??true,'approval_types'=>$latest->approval_types??null,'expense_open_time'=>$latest->expense_open_time??'11:00','expense_close_time'=>$latest->expense_close_time??'20:00'];
  DB::table('accounting_global_policy')->insert($values+['id'=>1,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('accounting_books')->update($values+['version'=>DB::raw('version + 1')]);
 }
 public function down():void {Schema::dropIfExists('accounting_global_policy');}
};
