<?php

use App\Http\Controllers\PortalController;
use App\Modules\Focus\Http\Controllers\FocusController;
use App\Modules\Focus\Services\FocusService;
use App\Services\PlatformAccess;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/focus-to-do', function (Request $r) {
    if (! PlatformAccess::product($r, 'focus-to-do')) {
        abort_if($r->user(), 403);
        $r->session()->put('url.intended', '/focus-to-do');

        return redirect('/login');
    }

    return app(PortalController::class)->index($r, 'focus-to-do');
});
Route::prefix('/focus-to-do/api')->group(function () {
    Route::get('data', [FocusController::class, 'data']);
    Route::post('tasks/{id?}', [FocusController::class, 'task'])->whereNumber('id');
    Route::post('tasks/{id}/complete', [FocusController::class, 'complete'])->whereNumber('id');
    Route::delete('tasks/{id}', [FocusController::class, 'deleteTask'])->whereNumber('id');
    Route::post('projects/{id?}', [FocusController::class, 'project'])->whereNumber('id');
    Route::delete('projects/{id}', [FocusController::class, 'deleteProject'])->whereNumber('id');
    Route::post('timer', [FocusController::class, 'timer']);
    Route::post('settings', [FocusController::class, 'settings']);
    Route::get('report', [FocusController::class, 'report']);
    Route::get('records', [FocusController::class, 'records']);
    Route::post('manual/{id?}', [FocusController::class, 'manual'])->whereNumber('id');
    Route::post('notifications', [FocusController::class, 'notifications']);
    Route::post('collect', [FocusController::class, 'collect']);
    Route::get('social', [FocusController::class, 'social']);
    Route::post('groups', [FocusController::class, 'group']);
    Route::post('clear', [FocusController::class, 'clear']);
    Route::get('realtime/config', function (Request $r) {
        $o = FocusService::owner($r);

        return response()->json(PlatformSettings::clientConfig() + ['channel' => 'focus.'.str_replace(':', '.', $o)])->header('Cache-Control', 'private, no-store');
    });
    Route::post('realtime/auth', function (Request $r) {
        $o = FocusService::owner($r);
        $r->validate(['socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'], 'channel_name' => 'required|string|max:150']);
        abort_unless($r->input('channel_name') === 'private-focus.'.str_replace(':', '.', $o), 403);
        abort_unless(in_array(config('broadcasting.default'), ['reverb', 'pusher'], true), 422, 'Realtime is disabled.');

        return Broadcast::connection()->validAuthenticationResponse($r, true);
    });
});
