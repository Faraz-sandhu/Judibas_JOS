<?php
namespace App\Http\Controllers;
use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use Illuminate\Http\Request;
class PlatformSettingsController extends Controller
{
    public function index(Request $request) {
        abort_unless($request->session()->get('judibas_admin'),403);
        $mail=MailSetting::current(); $realtime=RealtimeSetting::current();
        return response()->json(['mail'=>$mail->toArray()+['password'=>'','password_saved'=>filled($mail->password)],
            'realtime'=>$realtime->toArray()+['app_secret'=>'','secret_saved'=>filled($realtime->app_secret),'provider'=>$realtime->useReverb()?'reverb':'pusher'],
            'connection'=>\App\Services\PlatformSettings::clientConfig()])->header('Cache-Control','private, no-store');
    }
}
