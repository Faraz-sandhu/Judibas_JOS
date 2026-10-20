<?php

use App\Http\Controllers\PortalController;
use App\Modules\Daymark\Http\Controllers\CustomRoleController;
use App\Modules\Daymark\Http\Controllers\DailyExportController;
use App\Modules\Daymark\Http\Controllers\DaymarkController;
use App\Modules\Daymark\Http\Controllers\ManagementController;
use App\Modules\Daymark\Services\Access;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/daymark', function (Request $r) {
    if (! PlatformAccess::product($r, 'daymark')) {
        abort_if($r->user(), 403);
        $r->session()->put('url.intended', '/daymark');

        return redirect('/login');
    }

return app(PortalController::class)->index($r, 'daymark');
});
Route::prefix('/daymark/api')->group(function () {
    foreach ([
        ['GET', 'data', DaymarkController::class, 'data', 'view_reports'], ['GET', 'own-report', DaymarkController::class, 'ownReport', 'write_reports'],
        ['POST', 'report', DaymarkController::class, 'report', 'write_reports'], ['POST', 'review', DaymarkController::class, 'review', 'review_reports'],
        ['POST', 'password', ManagementController::class, 'password', 'view_reports'],
        ['GET', 'export', DaymarkController::class, 'export', 'export_reports'], ['GET', 'daily-export', DailyExportController::class, '__invoke', 'export_reports'],
        ['POST', 'departments', ManagementController::class, 'department', 'manage_departments'], ['POST', 'departments/delete', ManagementController::class, 'deleteDepartment', 'manage_departments'],
        ['POST', 'roles', CustomRoleController::class, 'store', 'manage_roles'], ['POST', 'user', ManagementController::class, 'user', 'manage_roles'],
        ['GET', 'invitations', ManagementController::class, 'invitations', 'manage_invitations'], ['POST', 'invite', ManagementController::class, 'invite', 'manage_invitations'], ['POST', 'revoke', ManagementController::class, 'revoke', 'manage_invitations'],
    ] as [$method,$path,$controller,$action,$ability]) {
        Route::match([$method], $path, function (Request $r) use ($controller, $action, $ability) {
            if (in_array($ability, ['manage_departments','manage_roles','manage_invitations'], true)) \App\Services\PlatformAccess::requireAdmin($r);
            Access::require($r, $ability);

            return app($controller)->$action($r);
        });
    }
});

Route::prefix('/admin/api/daymark')->group(function(){
 foreach ([['departments','department',ManagementController::class],['departments/delete','deleteDepartment',ManagementController::class],['roles','store',CustomRoleController::class]] as [$path,$action,$controller]) {
  Route::post($path,function(Request $r)use($controller,$action){\App\Services\PlatformAccess::requireAdmin($r);return app($controller)->$action($r);});
 }
});

Route::get('/daymark/api/realtime/config',function(Request $r){
 Access::require($r,'view_reports');
 return response()->json(\App\Services\PlatformSettings::clientConfig()+['channel'=>'daymark.user.'.(\App\Services\PlatformAccess::super($r)?'super':$r->user()->id)])->header('Cache-Control','private, no-store');
});
Route::post('/daymark/api/realtime/auth',function(Request $r){
 Access::require($r,'view_reports');
 $r->validate(['socket_id'=>['required','string','regex:/^\d+\.\d+$/'],'channel_name'=>'required|string|max:150']);
 $own='private-daymark.user.'.(\App\Services\PlatformAccess::super($r)?'super':$r->user()->id);
 abort_unless($r->input('channel_name')===$own,403);
 abort_unless(in_array(config('broadcasting.default'),['reverb','pusher'],true),422,'Realtime is disabled.');
 return \Illuminate\Support\Facades\Broadcast::connection()->validAuthenticationResponse($r,true);
});
