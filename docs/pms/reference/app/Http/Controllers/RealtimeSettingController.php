<?php

namespace App\Http\Controllers;

use App\Models\RealtimeSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pusher\Pusher;

class RealtimeSettingController extends Controller
{
    use AuthorizesRequests;

    public function edit(): View
    {
        $this->authorize('realtime-settings');

        return view('dashboard.settings.realtime', ['settings' => RealtimeSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('realtime-settings');
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'app_key' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:2000'],
            'cluster' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        $settings = RealtimeSetting::current();
        if (blank($validated['app_secret'] ?? null)) {
            unset($validated['app_secret']);
        }

        $settings->update($validated);
        cache()->forget('realtime.settings');
        $settings->refresh()->applyToConfig();

        if ($settings->enabled && ! $settings->isComplete()) {
            return back()->with('error', 'Realtime was saved, but complete Pusher credentials are required before it can connect.');
        }

        return back()->with('success', 'Realtime settings saved securely. Open pages must be refreshed to use changed credentials.');
    }

    public function test(Request $request): RedirectResponse
    {
        $this->authorize('realtime-settings');
        $settings = RealtimeSetting::current();
        $settings->applyToConfig();

        if (! $settings->enabled || ! $settings->isComplete()) {
            return back()->with('error', 'Enable realtime and save complete Pusher credentials before testing.');
        }

        try {
            $pusher = new Pusher(
                $settings->effectiveKey(),
                $settings->effectiveSecret(),
                $settings->effectiveAppId(),
                ['cluster' => $settings->effectiveCluster(), 'useTLS' => true]
            );
            $pusher->trigger(
                'private-App.Models.User.'.$request->user()->id,
                'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
                [
                    'subject' => 'Realtime connection successful',
                    'message' => 'This live notification confirms that the saved Pusher credentials are working.',
                    'activity' => 'test',
                    'type' => 'realtime_test',
                    'url' => route('settings.realtime.edit'),
                ]
            );

            return back()->with('success', 'Pusher accepted the test event. You should also see a live notification in this browser.');
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Pusher test failed: '.$exception->getMessage());
        }
    }
}
