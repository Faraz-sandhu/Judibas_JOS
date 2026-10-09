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
