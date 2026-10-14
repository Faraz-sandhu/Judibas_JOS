<?php
namespace App\Modules\Canteen\Services;
use App\Services\PlatformAccess;
use App\Services\CommunicationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CanteenAccess {
 public static function authorize(Request $r):void {if($r->attributes->get('canteen.authorized'))return;abort_unless(PlatformAccess::product($r,'canteen'),403,'Canteen access is required.');$r->attributes->set('canteen.authorized',true);}
 public static function companies(Request $r):array {self::authorize($r);if($r->attributes->has('canteen.company_ids'))return $r->attributes->get('canteen.company_ids');$ids=PlatformAccess::administrator($r)?DB::table('communication_companies')->pluck('id')->all():CommunicationAccess::companies($r->user()->id);$r->attributes->set('canteen.company_ids',$ids);return $ids;}
 public static function company(Request $r,int $id):void {abort_unless(in_array($id,self::companies($r)),403,'Company access is required.');}
 public static function role(Request $r,int $company):string {self::company($r,$company);return PlatformAccess::administrator($r)?'admin':(DB::table('canteen_profiles')->where('company_id',$company)->where('user_id',$r->user()->id)->value('role')??'employee');}
 public static function requireRole(Request $r,int $company,array $roles):void {abort_unless(in_array(self::role($r,$company),$roles),403,'You do not have permission for this Canteen action.');}
 public static function audit(Request $r,int $company,string $action,array $details):void {DB::table('canteen_audits')->insert(['company_id'=>$company,'user_id'=>$r->user()?->id,'actor'=>PlatformAccess::super($r)?'Super Admin':($r->user()?->name??'Invitation acceptance'),'action'=>$action,'details'=>json_encode($details),'created_at'=>now()]);}
}
