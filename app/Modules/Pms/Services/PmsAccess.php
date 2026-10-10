<?php
namespace App\Modules\Pms\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PmsAccess {
 public static function authorize(Request $r):void {if($r->session()->get('judibas_admin'))return;abort_unless($r->user()?->is_active && (int)DB::table('users')->where('id',$r->user()->id)->value('status')!==0 && DB::table('user_product_access')->where('user_id',$r->user()->id)->where('product_slug','projects')->exists(),403,'PMS access is required.');}
 public static function permissions(Request $r):array {self::authorize($r);if($r->attributes->has('pms.permissions'))return $r->attributes->get('pms.permissions');if($r->session()->get('judibas_admin'))return ['*'];$roles=DB::table('pms_role_users')->join('pms_roles','pms_roles.id','=','pms_role_users.role_id')->where('pms_role_users.user_id',$r->user()->id);if((clone $roles)->where('role_key','admin')->exists())return ['*'];$permissions=DB::table('pms_permission_roles')->join('pms_permissions','pms_permissions.id','=','pms_permission_roles.permission_id')->whereIn('role_id',$roles->pluck('pms_roles.id'))->distinct()->pluck('permission_key')->all();$r->attributes->set('pms.permissions',$permissions);return $permissions;}
 public static function allows(Request $r,string $key):bool {$p=self::permissions($r);return in_array('*',$p,true)||in_array($key,$p,true);}
 public static function requirePermission(Request $r,string $key):void {$permissions=self::permissions($r);abort_unless(in_array('*',$permissions,true)||in_array($key,$permissions,true),403);}
}
