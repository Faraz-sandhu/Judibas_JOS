<?php

namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Pulse;

class PulseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Pulse::query('Online & Offline Users', function () {
        //     return [
        //         'Online' => User::where('last_seen_at', '>=', now()->subMinutes(5))->count(),
        //         'Offline' => User::where('last_seen_at', '<', now()->subMinutes(5))->count(),
        //     ];
        // });
    }
}
