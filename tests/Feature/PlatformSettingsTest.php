<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use App\Services\PlatformSettings;
class PlatformSettingsTest extends TestCase
{
 use DatabaseTransactions;
 public function test_settings_require_super_admin_even_through_legacy_routes(): void {
  $this->getJson('/admin/api/platform-settings')->assertForbidden();
  foreach(['/admin/api/platform-settings/mail','/admin/api/platform-settings/realtime','/pms/api/settings/mail','/pms/api/settings/realtime'] as $url) $this->postJson($url,[])->assertForbidden();
 }
 public function test_shared_email_preserves_password_and_never_returns_secrets(): void {
  $mail=MailSetting::current();$mail->update(['password'=>'test-secret']);cache()->forget('pms.mail.settings');
  $this->withSession(['judibas_admin'=>true]);
  $this->postJson('/admin/api/platform-settings/mail',['host'=>'smtp.example.test','port'=>587,'scheme'=>'smtp','username'=>'sender','password'=>'','from_address'=>'sender@example.test','from_name'=>'Judibas','notification_emails_enabled'=>true,'invitation_emails_enabled'=>true,'daily_limit'=>300,'warning_threshold'=>270])->assertOk();
  $this->assertSame('test-secret',MailSetting::current()->password);
  $response=$this->getJson('/admin/api/platform-settings')->assertOk()->assertJsonPath('mail.password','')->assertJsonPath('mail.password_saved',true);
  $this->assertStringNotContainsString('test-secret',$response->getContent());
  PlatformSettings::apply();$this->assertSame('smtp.example.test',config('mail.mailers.smtp.host'));
  $this->assertSame(MailSetting::current()->id,\App\Modules\Pms\Models\MailSetting::current()->id);
 }
 public function test_realtime_is_shared_and_invalid_pusher_does_not_save(): void {
  $s=RealtimeSetting::current();$s->update(['enabled'=>true,'app_id'=>null,'app_key'=>null,'app_secret'=>null,'cluster'=>null]);cache()->forget('pms.realtime.settings');
  $this->withSession(['judibas_admin'=>true]);
  $this->postJson('/admin/api/platform-settings/realtime',['enabled'=>true,'provider'=>'pusher','app_id'=>'','app_key'=>'','cluster'=>''])->assertUnprocessable();
  $this->assertTrue(RealtimeSetting::current()->useReverb());
  $this->postJson('/admin/api/platform-settings/realtime',['enabled'=>true,'provider'=>'pusher','app_id'=>'test-id','app_key'=>'test-key','app_secret'=>'private-secret','cluster'=>'ap2'])->assertOk();
  $this->postJson('/admin/api/platform-settings/realtime',['enabled'=>true,'provider'=>'pusher','app_id'=>'test-id','app_key'=>'test-key','app_secret'=>'','cluster'=>'ap2'])->assertOk();
  $this->assertSame('private-secret',RealtimeSetting::current()->app_secret);
  PlatformSettings::apply();$this->assertSame('pusher',config('broadcasting.default'));
  $this->getJson('/communication/api/realtime/config')->assertOk()->assertJsonPath('provider','pusher')->assertJsonPath('key','test-key');
  $this->assertSame(PlatformSettings::clientConfig(),\App\Modules\Pms\Services\PmsRealtime::clientConfig());
  $this->assertStringNotContainsString('private-secret',$this->getJson('/admin/api/platform-settings')->getContent());
  $this->postJson('/admin/api/platform-settings/realtime',['enabled'=>false,'provider'=>'reverb'])->assertOk();
  PlatformSettings::apply();$this->assertSame('null',config('broadcasting.default'));
  $this->getJson('/communication/api/realtime/config')->assertJsonPath('enabled',false);
  $this->postJson('/broadcasting/auth',['socket_id'=>'1.2','channel_name'=>'private-communication.user.super'])->assertUnprocessable();
 }
}
