<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invitation;  

class InvitationPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $user, Invitation $invitation)
{
    return $user->id === $invitation->sender_id || $user->id === $invitation->recipient_id;
}
}
