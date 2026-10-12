<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PlatformAccess
{
 public static function super(Request $r):bool{return (bool)$r->session()->get('judibas_admin');}
 public static function administrator(Request $r):bool{return self::super($r)||($r->user()?->is_active&&(bool)$r->user()?->judibas_administrator);}
 public static function requireAdmin(Request $r):void{abort_unless(self::administrator($r),403);}
 public static function protectAccount(Request $r,?int $id):void{if($id&&!self::super($r))abort_if(DB::table('users')->where('id',$id)->where('judibas_administrator',true)->exists(),403,'Only Super Admin can change administrator accounts.');}
 /** Future products should use this shared access check before their product-specific permissions. */
 public static function product(Request $r,string $slug):bool{return self::administrator($r)||($r->user()?->is_active&&DB::table('user_product_access')->where('user_id',$r->user()->id)->where('product_slug',$slug)->exists());}
}
