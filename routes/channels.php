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
