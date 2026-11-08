<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('accounting_books', function(Blueprint $t){ $t->string('expense_open_time',5)->default('11:00'); $t->string('expense_close_time',5)->default('20:00'); }); }
 public function down(): void { Schema::table('accounting_books',fn(Blueprint $t)=>$t->dropColumn(['expense_open_time','expense_close_time'])); }
};
