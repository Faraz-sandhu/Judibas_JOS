<?php

use App\Services\CommunicationAccess;
use Illuminate\Support\Facades\Broadcast;

Broadcast::connection('reverb')->channel('communication.user.{id}', function ($user, string $id) {
    $r = request();
    if (CommunicationAccess::super($r)) {
        return $id === 'super';
    }
    CommunicationAccess::authorize($r);

    return (string) $user->id === $id;
});


Broadcast::connection('reverb')->channel('communication.presence.{id}', function ($user, string $id) {
 $r=request();CommunicationAccess::authorize($r);
 if(!CommunicationAccess::super($r)&&!in_array((int)$id,CommunicationAccess::eligible((int)$user->id),true))return false;
 return ['id'=>(string)$user->id];
});

Broadcast::connection('reverb')->channel('pms.project.chat.{id}',function($user,string $id){
 \App\Modules\Pms\Services\PmsAccess::authorize(request());
 $project=\App\Modules\Pms\Models\Projects::findOrFail($id);
 app(\App\Modules\Pms\Http\Controllers\ProjectChatController::class)->ensureProjectAccess($project);
 return true;
});

Broadcast::connection('reverb')->channel('pms.user.{id}',function($user,string $id){\App\Modules\Pms\Services\PmsAccess::authorize(request());return auth()->id()!==null && (string)auth()->id()===$id;});

Broadcast::connection('reverb')->channel('pms.global-update.{id}',function($user,string $id){\App\Modules\Pms\Services\PmsAccess::authorize(request());return request()->session()->get('judibas_admin') ? $id==='super' : (string)auth()->id()===$id;});

Broadcast::connection('reverb')->channel('pms.direct.{id}',function($user,string $id){\App\Modules\Pms\Services\PmsAccess::requirePermission(request(),'message-access');return request()->session()->get('judibas_admin') ? $id==='0' : (string)auth()->id()===$id;});
