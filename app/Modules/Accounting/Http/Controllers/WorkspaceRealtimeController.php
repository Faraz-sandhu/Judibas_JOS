<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess;
use App\Services\PlatformAccess;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class WorkspaceRealtimeController
{
    public function channels(Request $request): array
    {
        AccountingAccess::role($request);
        return PlatformAccess::administrator($request)
            ? ['accounting.workspace.admin']
            : AccountingAccess::companies($request)->map(fn ($company) => 'accounting.workspace.company.'.$company->id)->all();
    }

    public function config(Request $request)
    {
        return response()->json(PlatformSettings::clientConfig()+['channels'=>$this->channels($request)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function auth(Request $request)
    {
        $request->validate(['socket_id'=>['required','string','regex:/^\d+\.\d+$/'], 'channel_name'=>'required|string|max:150']);
        abort_unless(in_array($request->input('channel_name'), array_map(fn ($channel) => 'private-'.$channel, $this->channels($request)), true), 403);
        abort_unless(in_array(config('broadcasting.default'), ['reverb','pusher'], true), 422);
        return Broadcast::connection()->validAuthenticationResponse($request, true);
    }
}
