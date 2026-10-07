<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        require base_path('routes/channels.php');
        RateLimiter::for('portal-login', function (Request $r) {
            return [
                Limit::perMinute(5)->by('login:'.hash('sha256', strtolower((string) $r->input('email')).'|'.$r->ip())),
                Limit::perMinute(60)->by('login-ip:'.$r->ip()),
            ];
        });
        RateLimiter::for('communication-invite', fn (Request $r) => Limit::perMinute(10)->by('invite:'.($r->user()?->id ?? $r->session()->getId())));
        RateLimiter::for('invitation-accept', fn (Request $r) => Limit::perMinute(6)->by('accept:'.$r->ip().':'.hash('sha256', (string) $r->route('token'))));
        RateLimiter::for('communication-message', fn (Request $r) => Limit::perMinute(60)->by('message:'.($r->user()?->id ?? $r->session()->getId())));
    }
}
