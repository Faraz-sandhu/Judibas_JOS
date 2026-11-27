<?php
namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger, ApprovalFlow, VoucherNumber, VoucherTypes, ImprestFunds};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminRecordController
{
 public function action(Request $r,string $type,int $id)
 {
  if(in_array($type,['account','cheque','order'],true))return app(AdminExtendedRecordController::class)->action($r,$type,$id);
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can manage accounting records.');
  $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','action'=>'required|in:edit,delete','reason'=>'required|string|min:5|max:2000','posting_date'=>'nullable|date_format:Y-m-d']);
  $company=(int)$v['company_id'];Access::company($r,$company);
  abort_unless(in_array($type,['journal','invoice','payment','party','item','signed-copy'],true),404);
  $tables=['journal'=>'accounting_journals','invoice'=>'accounting_invoices','payment'=>'accounting_payments','party'=>'accounting_parties','item'=>'accounting_items','signed-copy'=>'accounting_signed_copies'];
  return DB::transaction(function()use($r,$v,$company,$type,$id,$tables){
   Ledger::lock($company);
   $row=DB::table($tables[$type])->where('company_id',$company)->where('id',$id)->lockForUpdate()->first();abort_unless($row,404);
   if($type!=='signed-copy')abort_unless((int)$row->version===(int)$v['version'],409,'This record changed. Reopen it before continuing.');
   abort_if(DB::table('accounting_admin_record_history')->where('company_id',$company)->where('record_type',$type)->where('record_id',$id)->where('action','delete')->exists(),409,'This record has already been deleted.');
   $snapshot=(array)$row;$replacement=null;$movement=null;$context=null;
   if($type==='payment'&&$v['action']==='edit'){
    abort_unless($row->status==='posted',422,'Record a new payment from the invoice for an already reversed payment.');
    abort_if(DB::table('accounting_cheques')->where('company_id',$company)->where('payment_key',$row->request_key)->exists(),422,'Edit this payment through its PDC record so cheque status remains consistent.');
    return ['message'=>'Review the replacement payment. The original changes only when the replacement saves.','context'=>['invoice_id'=>$row->invoice_id,'form'=>['replacement_payment_id'=>$id,'replacement_payment_version'=>$row->version,'reason'=>$v['reason'],'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'amount'=>Ledger::money($row->foreign_amount_minor??$row->amount_minor),'bank_account_id'=>$row->bank_account_id,'currency'=>$row->currency??Ledger::book($company)->currency,'exchange_rate'=>$row->exchange_rate??'1.000000','exchange_account_id'=>$row->exchange_account_id??null,'reference'=>$row->reference]]];
   }
   if($type==='signed-copy'){
    abort_unless($v['action']==='delete',422,'Use Upload replacement to change this proof.');
    abort_if($row->removed_at,409,'This proof has already been removed.');
    $a=DB::table('accounting_approvals')->where('company_id',$company)->where('id',$row->approval_id)->first();abort_unless($a,404);
    $currentCopy=ApprovalFlow::signedCopy($a);$reset=$a->stage!=='posted'&&($currentCopy?->id===$id||(int)$a->verified_copy_id===$id);
    DB::table($tables[$type])->where('id',$id)->update(['removed_at'=>now()]);
    if($reset)DB::table('accounting_approvals')->where('id',$a->id)->update(['stage'=>'paper_ceo','verified_copy_id'=>null,'verified_by'=>null,'verified_at'=>null,'version'=>$a->version+1,'updated_at'=>now()]);
    ApprovalFlow::event($r,$a->id,'signed_copy_removed',$a->stage,$reset?'paper_ceo':$a->stage,$v['reason']);
   }elseif(in_array($type,['party','item'],true)){
    abort_unless($v['action']==='delete',422,'Use the existing edit form to change this record.');
    // Retain references used by historical documents; archive from new-entry selections.
    DB::table($tables[$type])->where('id',$id)->update(['active'=>false,'version'=>$row->version+1,'updated_at'=>now()]);
    if($type==='party'&&$row->account_id)DB::table('accounting_accounts')->where('company_id',$company)->where('id',$row->account_id)->update(['active'=>false,'version'=>DB::raw('version+1'),'updated_at'=>now()]);
   }else{
    $date=$v['posting_date']??now()->toDateString();
    if($type==='journal'){
     Ledger::requireManual($company,$id);
     $movement=ImprestFunds::movement($id);
     if($movement)$snapshot['imprest']=(array)$movement;
     abort_if($row->reversal_of,422,'Correct the original document rather than its reversal entry.');
     $snapshot['lines']=DB::table('accounting_journal_lines')->where('journal_id',$id)->orderBy('position')->get()->map(fn($l)=>(array)$l)->all();
    }elseif($type==='invoice'){
     $snapshot['lines']=json_decode($row->lines,true);
     abort_if(DB::table('accounting_payments')->where('company_id',$company)->where('invoice_id',$id)->where('status','posted')->exists(),422,'Remove or reverse this invoice active payments first.');
     abort_if(DB::table('accounting_cheques')->where('company_id',$company)->where('invoice_id',$id)->whereIn('status',['pending','deposited','clearing'])->exists(),422,'Resolve this invoice outstanding cheques first.');
    }
    if($row->status==='posted'){
     $journal=$type==='journal'?$row:DB::table('accounting_journals')->where('company_id',$company)->where('id',$row->journal_id)->first();abort_unless($journal,409,'The source ledger entry is unavailable.');
     Ledger::reverse($r,$company,$journal->id,['version'=>$journal->version,'posting_date'=>$date,'reason'=>'Super Admin '.$v['action'].': '.$v['reason']]);
     if($type!=='journal')DB::table($tables[$type])->where('id',$id)->update(['status'=>'reversed','version'=>$row->version+1,'updated_at'=>now()]);
     if($type==='payment'){
      $cheque=DB::table('accounting_cheques')->where('company_id',$company)->where('payment_key',$row->request_key)->first();
      if($cheque){DB::table('accounting_cheques')->where('id',$cheque->id)->update(['status'=>'cancelled','version'=>$cheque->version+1,'updated_at'=>now()]);DB::table('accounting_cheque_events')->insert(['cheque_id'=>$cheque->id,'action'=>'payment_reversed','event_date'=>$date,'note'=>$v['reason'],'actor_id'=>$r->user()?->id,'created_at'=>now()]);}
     }
    }
    if($movement){
     DB::table('accounting_imprest_movements')->where('journal_id',$id)->update(['void'=>true]);
     if($v['action']==='edit'){
      $fund=DB::table('accounting_imprest_funds')->where('company_id',$company)->where('id',$movement->fund_id)->first();
      $offset=collect($snapshot['lines'])->first(fn($line)=>$line['account_id']!==$fund->account_id);
      $context=['type'=>$movement->type,'form'=>['fund_id'=>$fund->id,'account_id'=>$offset['account_id'],'amount'=>Ledger::money((int)collect($snapshot['lines'])->sum('debit_minor')),'posting_date'=>$date,'person'=>$row->voucher_person,'remarks'=>$row->remarks,'request_key'=>(string)Str::uuid()]];
     }
    }elseif($v['action']==='edit'){
     abort_unless($type!=='payment',422,'Remove or reverse this payment, then record its corrected replacement from the invoice.');
     if($row->status==='draft'){
      $a=ApprovalFlow::approval($company,$type,$id);
      if($a&&!in_array($a->stage,['returned','withdrawn'],true)){DB::table('accounting_approvals')->where('id',$a->id)->update(['stage'=>'withdrawn','version'=>$a->version+1,'updated_at'=>now()]);ApprovalFlow::event($r,$a->id,'withdraw',$a->stage,'withdrawn',$v['reason']);}
      $replacement=$id;
     }else{
      $new=(array)$row;foreach(['id','posted_at','posted_by','journal_id','reversal_of','reversal_reason'] as $key)unset($new[$key]);
      $new['request_key']=(string)Str::uuid();$new['status']='draft';$new['version']=1;$new['created_at']=$new['updated_at']=now();$new['created_by']=$r->user()?->id;
      $new['number']=VoucherNumber::next($company,$type==='journal'?(VoucherTypes::TYPES[$row->kind][0]??'JV'):($row->kind==='sale'?'SI':'PI'));
      if($type==='invoice'){$prefix=$row->kind==='sale'?'SI':'PI';$sequence=(int)DB::table('accounting_invoices')->where('company_id',$company)->count()+1;do{$new['number']=$prefix.'-'.str_pad((string)$sequence++,7,'0',STR_PAD_LEFT);}while(DB::table('accounting_invoices')->where('company_id',$company)->where('number',$new['number'])->exists());$new['journal_id']=null;abort_if(!empty($row->order_id),422,'Correct this order-linked invoice through its source order to preserve the company-to-company link.');}
      $replacement=DB::table($tables[$type])->insertGetId($new);
      if($type==='journal')foreach($snapshot['lines'] as $line){unset($line['id']);$line['journal_id']=$replacement;DB::table('accounting_journal_lines')->insert($line);}
     }
    }
   }
   if($v['action']==='delete'&&in_array($type,['journal','invoice'],true)){
    $approval=ApprovalFlow::approval($company,$type,$id);
    if($approval&&$approval->stage!=='posted'){
     DB::table('accounting_approvals')->where('id',$approval->id)->update(['stage'=>'withdrawn','version'=>$approval->version+1,'updated_at'=>now()]);
     ApprovalFlow::event($r,$approval->id,'withdraw',$approval->stage,'withdrawn',$v['reason']);
    }
   }
   DB::table('accounting_admin_record_history')->insert(['company_id'=>$company,'record_type'=>$type,'record_id'=>$id,'action'=>$v['action'],'actor_id'=>$r->user()?->id,'reason'=>trim($v['reason']),'snapshot'=>json_encode($snapshot),'created_at'=>now()]);
   Access::audit($r,$company,'Super Admin record '.$v['action'],$row->number??$row->name??$row->original_name,['type'=>$type,'id'=>$id,'reason'=>trim($v['reason']),'replacement_id'=>$replacement]);
   return ['message'=>$v['action']==='edit'?'Record ready for editing. Any previous posted amount was offset by a recorded reversal.':'Record removed from active records. History and any ledger reversal were retained.','id'=>$replacement,'context'=>$context];
  });
 }
}
