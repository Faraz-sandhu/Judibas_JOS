<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplicationSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        // Signalling and presence have separate limits and must stay responsive.
        $protectedWrite = ! $request->isMethodSafe()
            && $request->is('pms/api/*', 'accounting/api/*', 'daymark/api/*', 'focus-to-do/api/*', 'admin/*', 'cv-bank/*')
            && ! $request->is('*/realtime/auth', '*/notifications/realtime/auth');

        $protectedExport = $request->isMethod('GET') && (
            $request->is('pms/api/reports/export/*', 'pms/api/work-logs/export/*', 'daymark/api/export', 'daymark/api/daily-export')
            || ($request->is('accounting/api/reports') && $request->query('export') === 'csv')
        );

        $limiter = $protectedWrite ? 'application-writes' : ($protectedExport ? 'application-exports' : null);
        $response = $limiter
            ? app(ThrottleRequests::class)->handle($request, $next, $limiter)
            : $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if (! $response->headers->has('X-Frame-Options')) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Deliberately avoid script-src restrictions until inline scripts are migrated.
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'");
        }

        return $response;
    }
}
