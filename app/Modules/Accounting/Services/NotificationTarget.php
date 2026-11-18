<?php
namespace App\Modules\Accounting\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class NotificationTarget {
 public static function url(Request $r, object $n):?string {
  $company=(int)$n->company_id;$section=$n->section;$id=null;$permission='audit.view';
  $audit=DB::table('accounting_audits')->where('company_id',$company)->where('id',$n->audit_id)->first();
  $details=json_decode($audit->details??'{}',true)?:[];
  $subject=$audit->subject??$n->subject;
  $journal=DB::table('accounting_journals')->where('company_id',$company)->where('number',$n->subject)->first();
  if($journal){$id=$journal->id;$section=ImprestFunds::movement($id)?'imprest':(isset(VoucherTypes::TYPES[$journal->kind])?'single-vouchers':'journals');$permission=$section==='imprest'?'imprest.view':Permissions::journalGroup($journal->kind).'.view';}
  else {
   $invoice=DB::table('accounting_invoices')->where('company_id',$company)->where(function($q)use($subject,$n,$details){$q->where('number',$n->subject)->orWhere('number',$details['invoice']??'');if(preg_match('/^Invoice #(\d+)$/',$subject,$m))$q->orWhere('id',(int)$m[1]);})->first();
   if($invoice){$id=$invoice->id;$section=$invoice->kind==='sale'?'sales':'purchases';$permission=$section.'.view';}
   elseif(str_contains(strtolower($n->title),'contact')){
    $party=DB::table('accounting_parties')->where('company_id',$company)->where('name',$subject)->get();
    if($party->count()===1){$id=$party[0]->id;$section=$party[0]->kind==='customer'?'customers':'suppliers';$permission=$section.'.view';}
   }elseif(preg_match('/^PAYREQ-(\d+)$/',$n->subject,$m)){ $approval=DB::table('accounting_approvals')->where('company_id',$company)->where('document_type','payment')->where('document_id',(int)$m[1])->first();$id=$approval?->id;$section='approvals';$permission='workflow.view';
   }elseif($section==='roles'){$id=$details['role_id']??null;$permission='roles.view';
   }elseif($section==='chart'){$id=$details['account_id']??null;$permission='chart.view';}
   else $permission=['books'=>'books.view','roles'=>'roles.view','approvals'=>'workflow.view'][$section]??'audit.view';
  }
  if(!Permissions::can($r,$permission))return null;
  return \App\Services\CompanyWorkspace::url('accounting',$company).($id?'?'.http_build_query(['entry_id'=>$id]):'').'#'.$section;
 }
}
