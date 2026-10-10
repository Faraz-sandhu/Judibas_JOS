<?php

namespace App\Providers;

use App\Models\BrandingSetting;
use App\Models\MailSetting;
use App\Models\RealtimeSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Cache::rememberForever('schema.has_mail_settings', fn () => Schema::hasTable('mail_settings'))) {
            MailSetting::current()->applyToConfig();
        }
        if (Cache::rememberForever('schema.has_realtime_settings', fn () => Schema::hasTable('realtime_settings'))) {
            RealtimeSetting::current()->applyToConfig();
        }
        View::share('branding', Cache::rememberForever('schema.has_branding_settings', fn () => Schema::hasTable('branding_settings'))
            ? BrandingSetting::current()
            : new BrandingSetting());
    }
}
