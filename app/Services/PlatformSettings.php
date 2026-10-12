<?php
namespace App\Services;
use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
class PlatformSettings
{
    private static function tableExists(string $table):bool {
        if(app()->runningUnitTests())return Schema::hasTable($table);
        $key='platform.schema.'.hash('sha256',config('database.default').':'.config('database.connections.'.config('database.default').'.database').':'.$table);
        if(cache()->get($key)===true)return true;
        if(!Schema::hasTable($table))return false;
        cache()->put($key,true,3600);return true;
    }
    public static function apply(): void
    {
        if (!self::tableExists('pms_realtime_settings')) return;
        $settings = RealtimeSetting::current();
        config(['broadcasting.default' => !$settings->enabled ? 'null' : ($settings->useReverb() ? 'reverb' : 'pusher')]);
        if (!$settings->useReverb()) config(['broadcasting.connections.pusher' => [
            'driver'=>'pusher','key'=>$settings->effectiveKey(),'secret'=>$settings->effectiveSecret(),
            'app_id'=>$settings->effectiveAppId(),'options'=>['cluster'=>$settings->effectiveCluster(),'useTLS'=>true],
            'client_options'=>['connect_timeout'=>1,'timeout'=>2],
        ]]);
        Broadcast::forgetDrivers();
        if (self::tableExists('pms_mail_settings')) {
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
