<?php

namespace App\Http\Controllers;

use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use App\Services\PlatformSettings;
use App\Services\ProductCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformSettingsController extends Controller
{
    public function products(Request $request)
    {
        abort_unless($request->session()->get('judibas_admin'), 403);
        $v = $request->validate(['theme' => ['required', Rule::in(ProductCatalogue::THEMES)]]);
        DB::table('site_content')->updateOrInsert(['key' => 'products_theme'], ['value' => json_encode($v['theme']), 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['message' => 'Products page theme saved.', 'theme' => $v['theme']]);
    }

    public function index(Request $request)
    {
        abort_unless($request->session()->get('judibas_admin'), 403);
        $mail = MailSetting::current();
        $realtime = RealtimeSetting::current();

        return response()->json(['products' => ['theme' => ProductCatalogue::theme()], 'mail' => $mail->toArray() + ['password' => '', 'password_saved' => filled($mail->password)],
            'realtime' => $realtime->toArray() + ['app_secret' => '', 'secret_saved' => filled($realtime->app_secret), 'provider' => $realtime->useReverb() ? 'reverb' : 'pusher'],
            'connection' => PlatformSettings::clientConfig()])->header('Cache-Control', 'private, no-store');
    }
}
