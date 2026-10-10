<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthorityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
 
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('admin') || $user->hasRole('project_manager') || $user->hasRole('team_leader')) {
                return $next($request);
            }
            return response()->json(['message' => 'You are not authorized to perform this action'], 403);
        }
        return response()->json(['message' => 'You are not authorized to perform this action'], 403);
    }
}
