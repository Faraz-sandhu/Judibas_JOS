<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Modules\Pms\Models\Permission;
use App\Modules\Pms\Services\PmsAccess;
use Illuminate\Http\Request;
class PermissionController extends \App\Http\Controllers\Controller {
 public function index(){PmsAccess::requirePermission(request(),'permission-view');return response()->json(['data'=>Permission::orderBy('id')->get()]);}
 public function store(Request $r){PmsAccess::requirePermission($r,'permission-add');$v=$r->validate(['permission_name'=>'required|string|max:255','permission_key'=>'required|string|max:255|unique:pms_permissions,permission_key']);Permission::create($v);return response()->json(['message'=>'Permission created']);}
 public function update(Request $r,int $id){PmsAccess::requirePermission($r,'permission-edit');$v=$r->validate(['permission_name'=>'required|string|max:255']);Permission::findOrFail($id)->update($v);return response()->json(['message'=>'Permission updated']);}
 public function destroy(int $id){PmsAccess::requirePermission(request(),'permission-trash');Permission::findOrFail($id)->delete();return response()->json(['message'=>'Permission permanently deleted']);}
}
