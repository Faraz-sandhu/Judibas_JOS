<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Permissions};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController
{
    public function index(Request $r)
    {
        Access::role($r); Permissions::require($r, 'roles.view');
        return ['roles'=>DB::table('accounting_roles')->orderBy('name')->get()->map(fn ($role) => array_merge((array)$role,['permissions'=>json_decode($role->permissions,true),'assigned_users'=>DB::table('accounting_members')->where('role',$role->slug)->count()])),
            'catalog'=>Permissions::catalog(),'can_manage'=>Permissions::can($r,'roles.manage'),'grantable'=>Permissions::effective($r)];
    }

    public function save(Request $r)
    {
        Access::role($r); Permissions::require($r, 'roles.manage');
        $v=$r->validate(['id'=>'nullable|integer','version'=>'required_with:id|integer|min:1','name'=>'required|string|max:100','description'=>'nullable|string|max:2000','active'=>'required|boolean','permissions'=>'present|array','permissions.*'=>['required','string','distinct',Rule::in(Permissions::keys())]]);
        return DB::transaction(function()use($r,$v){
            // All role updates are serialised; permission changes take effect on the next request.
            DB::table('accounting_roles')->where('slug','manager')->lockForUpdate()->first();
            Permissions::require($r,'roles.manage');
            $old=isset($v['id'])?DB::table('accounting_roles')->find($v['id']):null;
            abort_if(isset($v['id'])&&!$old,404);
            abort_if($old?->protected,422,'Built-in roles are protected. Create a custom role with the required permissions.');
            abort_if($old&&(int)$old->version!==(int)$v['version'],409,'This role changed. Reopen it before saving.');
            abort_if($old&&!PlatformAccess::administrator($r)&&DB::table('accounting_members')->where('user_id',$r->user()->id)->where('role',$old->slug)->exists(),403,'You cannot change your own assigned role.');
            abort_if(array_diff($v['permissions'],Permissions::effective($r)),403,'You cannot grant permissions you do not hold.');
            $name=trim($v['name']);abort_if($name==='',422,'Enter a role name.');
            foreach($v['permissions'] as $permission){
                [$group,$action]=explode('.',$permission);
                if($action!=='view'&&in_array($group,['items','cheques','imprest','chart','customers','suppliers','sales','purchases','payments','journals','cash_receipts','cash_payments','bank_payments','bank_receipts','expense_vouchers','income_vouchers','roles','workflow','books','cash_reviews','purchase_orders','sales_orders']))abort_unless(in_array($group.'.view',$v['permissions']),422,'Enable View for '.$group.' before granting its actions.');
            }
            $values=['name'=>$name,'description'=>$v['description']??null,'permissions'=>json_encode($v['permissions']),'active'=>$v['active'],'version'=>($old->version??0)+1,'updated_at'=>now()];
            if($old){DB::table('accounting_roles')->where('id',$old->id)->update($values);$id=$old->id;}
            else $id=DB::table('accounting_roles')->insertGetId($values+['slug'=>substr(Str::slug($name)?:'role',0,65).'-'.Str::lower(Str::random(8)),'protected'=>false,'created_at'=>now()]);
            // Role administration is global within the product; audit each assigned company when available.
            $company=(int)(Access::companies($r)->first()->id??0);
            if($company)Access::audit($r,$company,$old?'Accounting role updated':'Accounting role created',$name,['previous'=>$old,'current'=>$values,'role_id'=>$id]);
            return ['message'=>'Accounting role saved.','role'=>DB::table('accounting_roles')->find($id)];
        });
    }
}
