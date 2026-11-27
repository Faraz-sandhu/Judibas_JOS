<?php
namespace App\Modules\Accounting\Services;
use Illuminate\Support\Facades\DB;
class AdminRecordHistory {
 public static function removed(string $type,int $company,?string $through=null){
  $latest=DB::table('accounting_admin_record_history')->where('company_id',$company)->where('record_type',$type)->when($through,fn($q)=>$q->where('created_at','<=',$through.' 23:59:59'))->selectRaw('MAX(id)')->groupBy('record_id');
  return DB::table('accounting_admin_record_history')->whereIn('id',$latest)->where('action','delete')->select('record_id');
 }
 public static function assertLive(string $type,int $company,int $id):void{abort_if(self::removed($type,$company)->where('record_id',$id)->exists(),409,'This record was deleted by Super Admin.');}
 public static function record($r,int $company,string $type,object $row,string $action,string $reason):void{
  DB::table('accounting_admin_record_history')->insert(['company_id'=>$company,'record_type'=>$type,'record_id'=>$row->id,'action'=>$action,'actor_id'=>$r->user()?->id,'reason'=>$reason,'snapshot'=>json_encode($row),'created_at'=>now()]);
  AccountingAccess::audit($r,$company,'Super Admin '.$type.' '.$action,$row->number??$row->code??$row->cheque_number??('#'.$row->id),['id'=>$row->id,'reason'=>$reason]);
 }
}
