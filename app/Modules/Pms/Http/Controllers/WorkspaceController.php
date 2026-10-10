<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PortalController;
use App\Modules\Pms\Services\PmsAccess;
use Illuminate\Http\Request;
class WorkspaceController extends Controller {
 public function page(Request $r){if(!$r->user()&&!$r->session()->get('judibas_admin'))return redirect('/login');PmsAccess::authorize($r);return app(PortalController::class)->index($r,'projects');}
 public function data(Request $r){return response()->json(['permissions'=>PmsAccess::permissions($r),'user_id'=>\App\Modules\Pms\Services\PmsAuth::id(),'name'=>\App\Modules\Pms\Services\PmsAuth::user()?->name,'branding'=>\App\Modules\Pms\Models\BrandingSetting::current()]);}
}
