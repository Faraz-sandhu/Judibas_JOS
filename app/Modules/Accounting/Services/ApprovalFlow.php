<?php

namespace App\Modules\Accounting\Services;

use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalFlow
{
    public const STAGES = ['paper_ceo'=>'Awaiting CEO-signed copy','finance_review'=>'Finance review','ceo_review'=>'CEO approval','finance_verify'=>'Finance verification','manager_review'=>'Finance Manager posting','posted'=>'Posted','returned'=>'Returned to preparer','withdrawn'=>'Withdrawn'];
    public const PERMISSIONS = ['paper_ceo'=>'workflow.upload_signed','finance_review'=>'workflow.finance_review','ceo_review'=>'workflow.ceo_approve','finance_verify'=>'workflow.finance_verify','manager_review'=>'workflow.finalize'];

    public static function enabled(int $company,?string $type=null,?int $id=null): bool { return $type!==null&&$id!==null?ApprovalPolicy::required($company,ApprovalPolicy::documentKey($company,$type,$id)):(bool)(DB::table('accounting_books')->where('company_id',$company)->value('approval_required')??false); }
    public static function approval(int $company,string $type,int $id): ?object { return DB::table('accounting_approvals')->where('company_id',$company)->where('document_type',$type)->where('document_id',$id)->first(); }
    public static function ensureEditable(int $company,string $type,int $id): void
    {
        $approval=self::approval($company,$type,$id);
        abort_if($approval&&!in_array($approval->stage,['returned','withdrawn']),409,'This document is in approval. Return or withdraw it before changing its accounts or amounts.');
    }
    public static function requirePosting(Request $r,int $company,string $type,int $id,int $version): void
    {
        $approval=self::approval($company,$type,$id);
        if(!self::enabled($company,$type,$id)&&(!$approval||in_array($approval->stage,['returned','withdrawn'])))return;
        $context=$r->attributes->get('accounting_approval');
        if($approval?->workflow_mode==='paper'){
            $copy=self::signedCopy($approval);abort_unless($copy&&\Illuminate\Support\Facades\Storage::disk('local')->exists($copy->path),422,'Attach an available CEO-signed copy for this saved voucher version before posting.');
        }
        abort_unless($approval&&$approval->stage==='manager_review'&&$approval->document_version===$version&&$context===[$approval->id,$type,$id,$version],422,'This document must complete the approval workflow before posting.');
    }

    public static function document(int $company,string $type,int $id): array
    {
        $table=match($type){'journal'=>'accounting_journals','invoice'=>'accounting_invoices','payment'=>'accounting_payment_requests',default=>abort(422,'Invalid approval document type.')};
        $row=DB::table($table)->where('company_id',$company)->where('id',$id)->first();abort_unless($row,404);
        if($type==='journal'){
            Ledger::requireManual($company,$id);$detail=Ledger::document($company,$id);$permission=Permissions::journalGroup($row->kind);
        }elseif($type==='invoice'){$detail=TradeDocuments::invoice($company,$id);$permission=$row->kind==='sale'?'sales':'purchases';}
        else{$payload=json_decode($row->payload,true);$invoice=DB::table('accounting_invoices')->where('company_id',$company)->where('id',$row->invoice_id)->first();$detail=(array)$row+['number'=>'PAYREQ-'.str_pad((string)$id,7,'0',STR_PAD_LEFT),'posting_date'=>$payload['posting_date'],'total'=>$payload['amount'],'party_name'=>$invoice->party_name,'reference'=>$payload['reference'],'payload'=>$payload];$permission='payments';}
        $base=Ledger::book($company)->currency;$detail['total_currency']=$type==='payment'?($payload['currency']??$base):$base;
        return ['row'=>$row,'detail'=>$detail,'permission'=>$permission];
    }

    public static function event(Request $r,int $id,string $action,?string $from,string $to,string $note): void
    {
        DB::table('accounting_approval_events')->insert(['approval_id'=>$id,'action'=>$action,'from_stage'=>$from,'to_stage'=>$to,'actor_id'=>$r->user()?->id,'actor_name'=>PlatformAccess::super($r)?'Super Admin':$r->user()->name,'note'=>$note,'created_at'=>now()]);
    }

    public static function submit(Request $r,int $company,string $type,int $id,int $version,string $note): int
    {
        Permissions::require($r,'workflow.submit');abort_unless(self::enabled($company,$type,$id),422,'Approval workflow is not enabled for this document type in Company books.');
        $document=self::document($company,$type,$id);$row=$document['row'];
        Permissions::require($r,$document['permission'].'.view');
        abort_unless($row->status==='draft'&&$row->version===$version,409,'Reopen the latest saved draft before submitting.');
        $old=self::approval($company,$type,$id);
        $stage=$type==='journal'?'paper_ceo':'finance_review';
        if($old&&$old->stage===$stage&&$old->document_version===$version)return $old->id;
        abort_if($old&&!in_array($old->stage,['returned','withdrawn']),409,'This document is already being approved.');
        $values=['verified_copy_id'=>null,'verified_by'=>null,'verified_at'=>null,'submission_version'=>($old->submission_version??0)+1,'workflow_mode'=>$type==='journal'?'paper':'online','document_version'=>$version,'stage'=>$stage,'prepared_by'=>$row->prepared_by??$row->created_by??$r->user()?->id,'ceo_by'=>null,'version'=>($old->version??0)+1,'updated_at'=>now()];
        if($old){DB::table('accounting_approvals')->where('id',$old->id)->update($values);$aid=$old->id;}
        else $aid=DB::table('accounting_approvals')->insertGetId($values+['company_id'=>$company,'document_type'=>$type,'document_id'=>$id,'created_at'=>now()]);
        self::event($r,$aid,'submit',$old->stage??null,$stage,$note);AccountingAccess::audit($r,$company,'Submitted for approval',$document['detail']['number'],['approval_id'=>$aid]);
        return $aid;
    }

    public static function signedCopy(object $approval): ?object
    {
        return DB::table('accounting_signed_copies')->where('company_id',$approval->company_id)->where('approval_id',$approval->id)->where('document_version',$approval->document_version)->where('submission_version',$approval->submission_version)->orderByDesc('id')->first();
    }

    public static function queuePayment(Request $r,int $company,int $invoice,array $payload): void
    {
        $old=DB::table('accounting_payment_requests')->where('company_id',$company)->where('request_key',$payload['request_key'])->first();
        if($old){abort_unless($old->invoice_id===$invoice&&json_decode($old->payload,true)===$payload&&$old->status!=='cancelled',409,'This payment request key belongs to another or cancelled payment.');return;}
        $reserved=DB::table('accounting_payment_requests')->where('company_id',$company)->where('invoice_id',$invoice)->where('status','draft')->get()->sum(fn($p)=>Ledger::minor(json_decode($p->payload,true)['amount']));
        $inv=TradeDocuments::invoice($company,$invoice);abort_if(Ledger::minor($payload['amount'])+$reserved>Ledger::minor($inv['foreign_outstanding']??$inv['outstanding']),422,'Posted and pending payments would exceed this invoice balance.');
        $id=DB::table('accounting_payment_requests')->insertGetId(['company_id'=>$company,'invoice_id'=>$invoice,'request_key'=>$payload['request_key'],'payload'=>json_encode($payload),'created_by'=>$r->user()?->id,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);
        self::submit($r,$company,'payment',$id,1,'Payment prepared and submitted for approval.');
    }
}
