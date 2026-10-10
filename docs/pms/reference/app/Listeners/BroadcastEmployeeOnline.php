<?php

namespace App\Listeners;

use App\Events\UserStatusUpdated;
use Illuminate\Auth\Events\Login;

class BroadcastEmployeeOnline
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event)
    {
        try {
            broadcast(new UserStatusUpdated($event->user, 'online'));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
