<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void { Schema::create('platform_backup_settings',function(Blueprint $t){$t->id();$t->boolean('enabled')->default(false);$t->string('frequency')->default('every_2_days');$t->string('time')->default('02:00');$t->string('timezone')->default('Asia/Karachi');$t->text('recipients');$t->unsignedInteger('retention')->default(14);$t->timestamp('last_run_at')->nullable();$t->string('last_status')->nullable();$t->text('last_error')->nullable();$t->timestamps();}); } public function down():void{Schema::dropIfExists('platform_backup_settings');} };
