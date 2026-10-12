<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\RealtimeSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pusher\Pusher;

class RealtimeSettingController extends Controller
{
    use AuthorizesRequests;

    public function edit()
    {
        abort_unless(request()->session()->get('judibas_admin'), 403);

        return view('dashboard.settings.realtime', ['settings' => RealtimeSetting::current()]);
    }

    public function update(Request $request)
    {
        abort_unless(request()->session()->get('judibas_admin'), 403);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['nullable', 'in:reverb,pusher'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'app_key' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:2000'],
            'cluster' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        $settings = RealtimeSetting::current();
        if (blank($validated['app_secret'] ?? null)) {
            unset($validated['app_secret']);
        }

        if (($validated['provider'] ?? null) === 'reverb') { foreach (['app_id','app_key','app_secret','cluster'] as $key) $validated[$key]=null; }
        $provider=$validated['provider'] ?? null; unset($validated['provider']);
        $candidate=clone $settings; $candidate->fill($validated);
        if ($candidate->enabled && (($provider==='pusher' && $candidate->useReverb()) || (!$candidate->useReverb() && !$candidate->isComplete()))) return response()->json(['message'=>'Complete Pusher credentials are required. Settings were not changed.'],422);
        $settings->update($validated);
        cache()->forget('pms.realtime.settings');

        if ($settings->enabled && ! $settings->useReverb() && ! $settings->isComplete()) {
            return response()->json(['message' => 'Realtime was saved, but complete Pusher credentials are required before it can connect.'], 422);
        }

        return response()->json(['message' => 'Realtime settings saved securely. Open pages must be refreshed to use changed credentials.']);
    }

    public function test(Request $request){abort_unless($request->session()->get('judibas_admin'),403);try{\App\Modules\Pms\Services\PmsRealtime::testConnection();return response()->json(['message'=>'Realtime server accepted the test event.']);}catch(\Throwable $e){report($e);return response()->json(['message'=>'Realtime connection failed. Check the saved settings and server.'],422);}}
}
