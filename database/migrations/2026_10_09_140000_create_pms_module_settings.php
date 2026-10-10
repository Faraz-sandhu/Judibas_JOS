<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('pms_mail_settings',function(Blueprint $t){$t->id();$t->string('host')->nullable();$t->unsignedSmallInteger('port')->default(587);$t->string('scheme')->nullable();$t->string('username')->nullable();$t->text('password')->nullable();$t->string('from_address')->nullable();$t->string('from_name')->nullable();$t->boolean('notification_emails_enabled')->default(true);$t->boolean('invitation_emails_enabled')->default(true);$t->unsignedInteger('daily_limit')->default(300);$t->unsignedInteger('warning_threshold')->default(270);$t->timestamps();});
  Schema::table('pms_invitations',function(Blueprint $t){$t->string('delivery_status',40)->default('pending')->index();$t->timestamp('emailed_at')->nullable();$t->text('email_error')->nullable();});
  Schema::create('pms_email_deliveries',function(Blueprint $t){$t->id();$t->string('type',40)->index();$t->string('recipient');$t->foreignId('invitation_id')->nullable()->constrained('pms_invitations')->nullOnDelete();$t->string('status',40)->index();$t->text('error')->nullable();$t->timestamp('sent_at')->nullable()->index();$t->timestamps();$t->index(['type','created_at']);});
  Schema::create('pms_realtime_settings',function(Blueprint $t){$t->id();$t->boolean('enabled')->default(true);$t->string('app_id')->nullable();$t->string('app_key')->nullable();$t->text('app_secret')->nullable();$t->string('cluster',50)->nullable();$t->timestamps();});
  Schema::create('pms_branding_settings',function(Blueprint $t){$t->id();foreach(['sidebar_logo','sidebar_logo_text','sidebar_logo_text_dark','login_logo','login_logo_dark','login_cover','login_cover_dark','favicon'] as $field)$t->string($field)->nullable();$t->timestamps();});
  Schema::table('pms_task_comments',function(Blueprint $t){$t->unsignedBigInteger('user_id')->nullable()->change();$t->string('author_name')->nullable();});
 }
 public function down():void {Schema::dropIfExists('pms_branding_settings');Schema::dropIfExists('pms_realtime_settings');Schema::dropIfExists('pms_email_deliveries');Schema::table('pms_invitations',fn(Blueprint $t)=>$t->dropColumn(['delivery_status','emailed_at','email_error']));Schema::dropIfExists('pms_mail_settings');Schema::table('pms_task_comments',fn(Blueprint $t)=>$t->dropColumn('author_name'));}
};
