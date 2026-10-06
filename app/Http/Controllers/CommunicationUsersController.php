<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CommunicationUsersController extends Controller {
 public function data(Request $r) { $all=app(AdminController::class)->data($r)->getData(true); return response()->json(['users'=>$all['users'],'companies'=>$all['companies'],'products'=>array_values(array_filter($all['products'],fn($p)=>$p['slug']==='communication'))]); }
 public function save(Request $r, ?int $id=null) { abort_unless($r->session()->get('judibas_admin'),403); $r->validate(['product_slugs'=>'present|array','product_slugs.*'=>'in:communication']); $other=$id?DB::table('user_product_access')->where('user_id',User::findOrFail($id)->id)->where('product_slug','!=','communication')->pluck('product_slug')->all():[]; $r->merge(['product_slugs'=>array_merge($other,$r->input('product_slugs'))]); return app(AdminController::class)->save($r,'users',$id); }
 public function remove(Request $r,int $id) { abort_unless($r->session()->get('judibas_admin'),403); User::findOrFail($id); DB::transaction(function()use($id){ DB::table('user_product_access')->where('user_id',$id)->where('product_slug','communication')->delete(); DB::table('users')->where('id',$id)->update(['communication_admin'=>false]); DB::table('communication_members')->where('user_id',$id)->delete(); }); return response()->json(['message'=>'Communication access removed. The employee account and other product access are preserved.']); }
}