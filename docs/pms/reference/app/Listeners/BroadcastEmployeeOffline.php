<?php

namespace App\Listeners;

use App\Events\UserStatusUpdated;
use Illuminate\Auth\Events\Logout;

class BroadcastEmployeeOffline
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
    public function handle(Logout $event)
    {
        if (! $event->user) {
            return;
        }

        $event->user->update(['last_seen_at' => now()]);
        try {
            broadcast(new UserStatusUpdated($event->user, 'offline'));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
