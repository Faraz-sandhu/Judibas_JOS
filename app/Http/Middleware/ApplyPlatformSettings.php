<?php
namespace App\Http\Middleware;
use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
class ApplyPlatformSettings
{
    public function handle(Request $request, Closure $next) { if (!app()->runningUnitTests()) PlatformSettings::apply(); return $next($request); }
}
