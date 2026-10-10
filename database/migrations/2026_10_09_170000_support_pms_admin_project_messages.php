<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('pms_project_chats',function(Blueprint $table){$table->unsignedBigInteger('user_id')->nullable()->change();$table->string('author_name')->nullable();});}
 public function down():void {Schema::table('pms_project_chats',function(Blueprint $table){$table->dropColumn('author_name');});}
};
