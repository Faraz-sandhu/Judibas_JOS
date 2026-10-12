<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('platform_invitations',function(Blueprint $t){$t->id();$t->string('email',200)->index();$t->string('name',100)->nullable();$t->string('token_hash',64)->unique();$t->json('access');$t->timestamp('expires_at');$t->timestamp('accepted_at')->nullable();$t->timestamp('revoked_at')->nullable();$t->string('delivery_status')->default('manual');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('platform_invitations');}
};
