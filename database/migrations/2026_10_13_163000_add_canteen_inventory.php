<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('canteen_items',function(Blueprint $t){$t->boolean('stock_enabled')->default(false);$t->unsignedInteger('stock_quantity')->default(0);$t->unsignedInteger('low_stock_threshold')->default(5);});Schema::create('canteen_stock_movements',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained('communication_companies');$t->foreignId('item_id')->constrained('canteen_items');$t->foreignId('order_id')->nullable()->constrained('canteen_orders');$t->integer('quantity_change');$t->unsignedInteger('balance');$t->string('reason',250);$t->timestamp('created_at')->useCurrent();});Schema::table('canteen_order_lines',fn(Blueprint $t)=>$t->boolean('stock_tracked')->default(false));}
 public function down():void {Schema::table('canteen_order_lines',fn(Blueprint $t)=>$t->dropColumn('stock_tracked'));Schema::dropIfExists('canteen_stock_movements');Schema::table('canteen_items',fn(Blueprint $t)=>$t->dropColumn(['stock_enabled','stock_quantity','low_stock_threshold']));}
};
