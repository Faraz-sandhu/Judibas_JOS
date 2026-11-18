<?php
namespace App\Modules\Accounting\Services;
use App\Modules\Accounting\Events\ActivityChanged;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityNotifications
{
    public static function viewer(Request $r):string {return PlatformAccess::super($r)?'super':'user:'.$r->user()->id;}
    public static function authorize(Request $r):void {Permissions::require($r,'notifications.view');}
    public static function channels(Request $r):array
    {
        self::authorize($r);
        return PlatformAccess::administrator($r)?['accounting.activity.admin']:AccountingAccess::companies($r)->map(fn($c)=>'accounting.activity.company.'.$c->id)->all();
    }
    public static function query(Request $r,?int $company=null)
    {
        self::authorize($r);$viewer=self::viewer($r);
        if($company)AccountingAccess::company($r,$company,'notifications.view');
        $q=DB::table('accounting_activity_notifications as n')->join('communication_companies as c','c.id','=','n.company_id')
            ->leftJoin('accounting_notification_reads as r',fn($join)=>$join->on('r.notification_id','=','n.id')->where('r.viewer_key',$viewer))->where('n.actor_key','<>',$viewer);
        if(!PlatformAccess::administrator($r))$q->whereIn('n.company_id',AccountingAccess::companies($r)->pluck('id'));
        if($company)$q->where('n.company_id',$company);
        return $q;
    }
    public static function record(Request $r,int $audit,int $company,string $action,string $subject,string $actor):void
    {
        if($action==='Approval post')return; // The underlying financial posting already records its notification.
        $section='audit';$display=$subject;
        $journalId=preg_match('/^Journal #(\d+)$/',$subject,$m)?(int)$m[1]:null;
        $journal=DB::table('accounting_journals')->where('company_id',$company)->when($journalId,fn($q)=>$q->where('id',$journalId),fn($q)=>$q->where('number',$subject))->first(['id','kind','number']);
        if($journal){$section=ImprestFunds::movement($journal->id)?'imprest':(isset(VoucherTypes::TYPES[$journal->kind])?'single-vouchers':'journals');$display=$journal->number;}
        elseif(preg_match('/^IC-(\d+)$/',$subject,$orderMatch)){ $order=DB::table('accounting_intercompany_orders')->find((int)$orderMatch[1]);if($order){$section=(int)$order->buyer_company_id===$company?'purchase-orders':'sales-orders';}}
        elseif(str_contains(strtolower($action),'role'))$section='roles';
        elseif(str_contains(strtolower($action),'books'))$section='books';
        elseif(str_contains(strtolower($action),'account')||str_contains(strtolower($action),'chart'))$section='chart';
        elseif(str_contains(strtolower($action),'approval'))$section='approvals';
        elseif(str_contains(strtolower($action),'invoice')){
            $invoice=DB::table('accounting_invoices')->where('company_id',$company)->where(function($q)use($subject){$q->where('number',$subject);if(preg_match('/^Invoice #(\d+)$/',$subject,$m))$q->orWhere('id',(int)$m[1]);})->first(['number','kind']);
            if($invoice){$display=$invoice->number;$section=$invoice->kind==='sale'?'sales':'purchases';}
        }
        $title=['Approval post'=>'Document posted','Approval return'=>'Returned for correction','Approval withdraw'=>'Approval withdrawn','Approval approve'=>'Approval forwarded','CEO-signed voucher copy attached'=>'CEO-signed copy attached'][$action]??$action;
        $id=DB::table('accounting_activity_notifications')->insertGetId(['company_id'=>$company,'audit_id'=>$audit,'actor_key'=>self::viewer($r),'actor_name'=>$actor,'title'=>$title,'subject'=>$display,'section'=>$section,'created_at'=>now()]);
        DB::afterCommit(function()use($company,$id){try{event(new ActivityChanged($company,$id));}catch(\Throwable $e){report($e);}});
    }
}
