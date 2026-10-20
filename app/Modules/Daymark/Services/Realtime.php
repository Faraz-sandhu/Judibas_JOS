<?php
namespace App\Modules\Daymark\Services;
use App\Modules\Daymark\Events\ReportChanged;
use App\Modules\Daymark\Models\{Report,User};
use Illuminate\Support\Facades\DB;
class Realtime {
 /** Report contents are never broadcast. Recipients reload through the scoped API. */
 public static function recipients(Report $report):array {
  $ids=[];
  foreach(User::where('company_id',$report->user->company_id)->get() as $viewer){
   if($viewer->active&&in_array($viewer->company_id,\App\Services\CommunicationAccess::companies($viewer->id),true)&&RoleAccess::allows($viewer,'view_reports')&&Report::visibleTo($viewer)->whereKey($report->id)->exists())$ids[]=(string)$viewer->id;
  }
  if($report->status!=='draft'){
   $ids[]='super';
   foreach(DB::table('users')->where('judibas_administrator',true)->where('is_active',true)->pluck('id') as $id)$ids[]=(string)$id;
  }
  return array_values(array_unique($ids));
 }
 /** Called after the report transaction has completed successfully. */
 public static function publish(Report $report,string $action):void {
  foreach(array_chunk(self::recipients($report),100) as $recipients){
   try{event(new ReportChanged($recipients,$report->id,$action));}catch(\Throwable $e){report($e);}
  }
 }
}
