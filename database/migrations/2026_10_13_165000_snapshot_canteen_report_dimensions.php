<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::table('canteen_order_lines',fn(Blueprint $t)=>$t->foreignId('category_id')->nullable()->constrained('canteen_categories')->restrictOnDelete());Schema::table('canteen_orders',fn(Blueprint $t)=>$t->foreignId('level_id')->nullable()->constrained('canteen_levels')->restrictOnDelete());}public function down():void {Schema::table('canteen_order_lines',fn(Blueprint $t)=>$t->dropConstrainedForeignId('category_id'));Schema::table('canteen_orders',fn(Blueprint $t)=>$t->dropConstrainedForeignId('level_id'));}};
