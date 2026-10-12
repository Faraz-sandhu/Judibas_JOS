<?php
namespace App\Services;
use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
class PlatformSettings
{
    public static function apply(): void
    {
        if (!Schema::hasTable('pms_realtime_settings')) return;
        $settings = RealtimeSetting::current();
        config(['broadcasting.default' => !$settings->enabled ? 'null' : ($settings->useReverb() ? 'reverb' : 'pusher')]);
        if (!$settings->useReverb()) config(['broadcasting.connections.pusher' => [
            'driver'=>'pusher','key'=>$settings->effectiveKey(),'secret'=>$settings->effectiveSecret(),
            'app_id'=>$settings->effectiveAppId(),'options'=>['cluster'=>$settings->effectiveCluster(),'useTLS'=>true],
            'client_options'=>['connect_timeout'=>1,'timeout'=>2],
        ]]);
        Broadcast::forgetDrivers();
        if (Schema::hasTable('pms_mail_settings')) {
            $mail = MailSetting::current();
            if ($mail->host && $mail->from_address) { $mail->applyToConfig(); Mail::purge('smtp'); }
        }
    }
    public static function clientConfig(): array
    {
        $s=RealtimeSetting::current(); $reverb=$s->useReverb(); $c=config('broadcasting.connections.reverb');
        return ['enabled'=>$s->enabled&&($reverb||$s->isComplete()),'provider'=>$reverb?'reverb':'pusher',
            'key'=>$reverb?$c['key']:$s->effectiveKey(),'cluster'=>$reverb?'mt1':$s->effectiveCluster(),
            'host'=>env('VITE_REVERB_HOST')?:request()->getHost(),'port'=>$c['options']['port'],'scheme'=>$c['options']['scheme']];
    }
}
