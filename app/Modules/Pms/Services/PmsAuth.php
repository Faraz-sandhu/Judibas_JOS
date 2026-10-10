<?php
namespace App\Modules\Pms\Services;
use App\Modules\Pms\Models\User;
class PmsAuth {
 public static function user():?User {if(request()->session()->get('judibas_admin')){$u=new User;$u->setRawAttributes(['id'=>null,'name'=>'Super Admin','status'=>1,'pms_super'=>true]);$role=new \App\Modules\Pms\Models\Role;$role->setRawAttributes(['role_key'=>'admin']);$u->setRelation('roles',collect([$role]));return $u;}return \Illuminate\Support\Facades\Auth::id()?User::find(\Illuminate\Support\Facades\Auth::id()):null;}
 public static function __callStatic($method,$args){return \Illuminate\Support\Facades\Auth::$method(...$args);}
 public static function id():?int {return \Illuminate\Support\Facades\Auth::id();}
}
