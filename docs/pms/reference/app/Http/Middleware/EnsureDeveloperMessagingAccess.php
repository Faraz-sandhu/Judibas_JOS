<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeveloperMessagingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $hasAccess = $user && $user->can('message-access');
        abort_unless($hasAccess, 403);

        return $next($request);
    }
}
