<?php
namespace App\Modules\Pms\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Modules\Pms\Services\PmsAccess;
class AuthorizeWorkspace { public function handle(Request $request, Closure $next) { PmsAccess::authorize($request); if($request->user()&&!$request->session()->get('judibas_admin'))\Illuminate\Support\Facades\DB::table('users')->where('id',$request->user()->id)->where(fn($q)=>$q->whereNull('last_seen_at')->orWhere('last_seen_at','<',now()->subMinute()))->update(['last_seen_at'=>now()]); return $next($request); } }
