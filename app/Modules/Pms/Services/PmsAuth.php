<?php
namespace App\Modules\Pms\Services;
use App\Modules\Pms\Models\User;
class PmsAuth {
 public static function user():?User {if(request()->attributes->has('pms.actor'))return request()->attributes->get('pms.actor');$actor=self::resolveUser();request()->attributes->set('pms.actor',$actor);return $actor;}
 private static function resolveUser():?User {if(request()->session()->get('judibas_admin')){$u=new User;$u->setRawAttributes(['id'=>null,'name'=>'Super Admin','status'=>1,'pms_super'=>true]);$role=new \App\Modules\Pms\Models\Role;$role->setRawAttributes(['role_key'=>'admin']);$u->setRelation('roles',collect([$role]));return $u;}if(\App\Services\PlatformAccess::administrator(request())){$u=User::withoutGlobalScopes()->find(\Illuminate\Support\Facades\Auth::id());$u?->setAttribute('pms_super',true);$role=new \App\Modules\Pms\Models\Role;$role->setRawAttributes(['role_key'=>'admin','role_name'=>'Judibas Admin']);$u?->setRelation('roles',collect([$role]));return $u;}return \Illuminate\Support\Facades\Auth::id()?User::find(\Illuminate\Support\Facades\Auth::id()):null;}
 public static function __callStatic($method,$args){return \Illuminate\Support\Facades\Auth::$method(...$args);}
 public static function id():?int {return \Illuminate\Support\Facades\Auth::id();}
}
