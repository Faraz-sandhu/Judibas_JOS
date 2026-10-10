<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('pms_departments',function(Blueprint $t){$t->id();$t->string('dept_name')->unique();$t->longText('description')->nullable();$t->smallInteger('status')->default(1);$t->timestamps();});
  Schema::create('pms_roles',function(Blueprint $t){$t->id();$t->string('role_name')->unique();$t->string('role_key');$t->timestamps();});
  Schema::create('pms_permissions',function(Blueprint $t){$t->id();$t->string('permission_name')->unique();$t->string('permission_key')->unique();$t->timestamps();});
  Schema::create('pms_permission_roles',function(Blueprint $t){$t->id();$t->foreignId('role_id')->constrained('pms_roles')->cascadeOnDelete();$t->foreignId('permission_id')->constrained('pms_permissions')->cascadeOnDelete();$t->timestamps();});
  Schema::create('pms_role_users',function(Blueprint $t){$t->id();$t->foreignId('role_id')->constrained('pms_roles')->cascadeOnDelete();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();$t->timestamps();});
 }
 public function down():void {foreach(['pms_role_users','pms_permission_roles','pms_permissions','pms_roles','pms_departments'] as $table)Schema::dropIfExists($table);}
};
