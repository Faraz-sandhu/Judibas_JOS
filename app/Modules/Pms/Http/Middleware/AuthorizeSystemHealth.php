<?php
namespace App\Modules\Pms\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Modules\Pms\Services\PmsAccess;
class AuthorizeSystemHealth {
 public function handle(Request $request, Closure $next) {
  PmsAccess::authorize($request);
  PmsAccess::requirePermission($request, 'pulse-view');
  return $next($request);
 }
}
