<?php
namespace App\Modules\Accounting\Http\Controllers;
use App\Modules\Accounting\Services\{AccountingAccess as Access,AdminRecordHistory as History,Ledger,TradeDocuments,Currency};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminExtendedRecordController
{
 private function authorize(Request $r,int $company):void{abort_unless(PlatformAccess::super($r),403,'Only Super Admin can manage these records.');Access::company($r,$company);}
 public function action(Request $r,string $type,int $id){
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can manage these records.');
  $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','action'=>'required|in:edit,delete,restore','reason'=>'required|string|min:5|max:2000','posting_date'=>'nullable|date_format:Y-m-d|before_or_equal:today']);
  $company=(int)$v['company_id'];$this->authorize($r,$company);
  return DB::transaction(function()use($r,$v,$company,$type,$id){
   // Linked orders lock both companies in consistent order.
   if($type==='order'){
    $order=DB::table('accounting_intercompany_orders')->where('id',$id)->where(fn($q)=>$q->where('seller_company_id',$company)->orWhere('buyer_company_id',$company))->first();abort_unless($order,404);
    foreach(collect([$order->seller_company_id,$order->buyer_company_id])->sort() as $c){$this->authorize($r,$c);Ledger::lock($c);}
   }else Ledger::lock($company);
   $table=['account'=>'accounting_accounts','cheque'=>'accounting_cheques','order'=>'accounting_intercompany_orders'][$type]??null;abort_unless($table,404);
   $row=DB::table($table)->where('id',$id)->when($type!=='order',fn($q)=>$q->where('company_id',$company))->lockForUpdate()->first();abort_unless($row,404);
   abort_unless((int)$row->version===(int)$v['version'],409,'This record changed. Reopen it before continuing.');
   if($v['action']!=='restore')History::assertLive($type,$company,$id);
   if($type==='account'){
    abort_unless($v['action']!=='edit',422,'Use the Super Admin account edit form.');
    $ids=[$id];$frontier=[$id];
    while($frontier){$frontier=DB::table('accounting_accounts')->where('company_id',$company)->whereIn('parent_id',$frontier)->whereNotIn('id',$ids)->pluck('id')->all();$ids=array_merge($ids,$frontier);}
    foreach(DB::table($table)->where('company_id',$company)->whereIn('id',$ids)->orderBy('id')->get() as $account){
     $removed=History::removed('account',$company)->where('record_id',$account->id)->exists();
     if(($v['action']==='delete'&&$removed)||($v['action']==='restore'&&!$removed))continue;
     if($v['action']==='restore'&&$account->parent_id&&!in_array($account->parent_id,$ids,true))History::assertLive('account',$company,$account->parent_id);
     $party=DB::table('accounting_parties')->where('company_id',$company)->where('account_id',$account->id)->first();
     $fund=DB::table('accounting_imprest_funds')->where('company_id',$company)->where('account_id',$account->id)->first();
     $previous=null;if($v['action']==='restore'){$deleted=DB::table('accounting_admin_record_history')->where('company_id',$company)->where('record_type','account')->where('record_id',$account->id)->where('action','delete')->orderByDesc('id')->first();$previous=json_decode($deleted->snapshot,true);}
     if($v['action']==='restore'&&$account->id!==$id&&isset($previous['archive_root_id'])&&(int)$previous['archive_root_id']!==$id)continue;
     $account->archive_root_id=$id;
     $active=$v['action']==='restore'?(bool)($previous['active']??true):false;
     $partyRemoved=$party&&History::removed('party',$company)->where('record_id',$party->id)->exists();if($partyRemoved)$active=false;
     $account->linked_party=$party?['id'=>$party->id,'active'=>$party->active]:null;$account->linked_fund=$fund?['id'=>$fund->id,'active'=>$fund->active]:null;
     DB::table($table)->where('id',$account->id)->update(['active'=>$active,'version'=>$account->version+1,'updated_at'=>now()]);
     if($party)DB::table('accounting_parties')->where('id',$party->id)->update(['active'=>$v['action']==='restore'?(!$partyRemoved&&(bool)($previous['linked_party']['active']??$active)):false,'version'=>$party->version+1,'updated_at'=>now()]);
     if($fund)DB::table('accounting_imprest_funds')->where('id',$fund->id)->update(['active'=>$v['action']==='restore'?(bool)($previous['linked_fund']['active']??$active):false,'updated_at'=>now()]);
     History::record($r,$company,'account',$account,$v['action'],$v['reason']);
    }
    return ['message'=>$v['action']==='restore'?'Account hierarchy restored.':'Account and its children removed from the active chart. Ledger history is preserved.'];
   }
   abort_if($v['action']==='restore',422,'Restore is available for archived accounts.');
   if($type==='order'){
    if($v['action']==='edit')return ['message'=>'Review the replacement order details.','context'=>['form'=>['replacement_of'=>$id,'replacement_version'=>$row->version,'reason'=>$v['reason'],'request_key'=>(string)Str::uuid(),'seller_company_id'=>$row->seller_company_id,'buyer_company_id'=>$row->buyer_company_id,'order_date'=>$row->order_date,'due_date'=>$row->due_date,'terms'=>$row->terms,'lines'=>json_decode($row->lines,true)]]];
    $this->removeOrder($r,$row,$v['reason'],$v['posting_date']??now()->toDateString());
    return ['message'=>'Both order sides and linked invoices removed from active records; posted amounts were offset by reversals.'];
   }
   if($v['action']==='edit')return ['message'=>'Review the cheque correction.','context'=>['form'=>array_merge((array)$row,['amount'=>Ledger::money($row->amount_minor),'reason'=>$v['reason']])]];
   $this->unwindCheque($r,$row,$v['posting_date']??now()->toDateString(),$v['reason']);
   DB::table($table)->where('id',$id)->update(['status'=>'cancelled','version'=>$row->version+1,'updated_at'=>now()]);
   $this->chequeEvent($r,$id,'cancel',$v['posting_date']??now()->toDateString(),$v['reason']);
   History::record($r,$company,$type,$row,'delete',$v['reason']);
   return ['message'=>'Cheque removed. Any cleared payment was reversed and invoice outstanding updated.'];
  });
 }
 public function saveAccount(Request $r,int $id){
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can manage these records.');
  $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','name'=>'required|string|max:150','code'=>['required','string','max:255','regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/'],'description'=>'nullable|string|max:2000','parent_id'=>'nullable|integer','active'=>'required|boolean','reason'=>'required|string|min:5|max:2000']);
  $company=(int)$v['company_id'];$this->authorize($r,$company);
  return DB::transaction(function()use($r,$id,$v,$company){
   Ledger::lock($company);History::assertLive('account',$company,$id);
   $old=DB::table('accounting_accounts')->where('company_id',$company)->where('id',$id)->lockForUpdate()->first();abort_unless($old,404);abort_unless($old->version===(int)$v['version'],409,'Account changed. Reopen it.');
   $name=trim($v['name']);$code=strtoupper(trim($v['code']));abort_unless($name!==''&&$code!=='',422,'Enter an account name and code.');
   abort_if(DB::table('accounting_accounts')->where('company_id',$company)->where('code',$code)->where('id','<>',$id)->exists(),422,'This code is already used by another account, including archived accounts.');
   $parent=$v['parent_id']??null;
   if($old->is_system)abort_if($parent!==null,422,'Root groups cannot be placed inside another account.');
   else{
    $p=DB::table('accounting_accounts')->where('company_id',$company)->where('id',$parent)->first();
    abort_unless($p&&$p->is_group&&$p->root_type===$old->root_type,422,'Choose a group of the same root type in this company.');History::assertLive('account',$company,$p->id);
    abort_if($v['active']&&!$p->active,422,'Activate the parent group first.');$seen=[$id];$cursor=$p;
    while($cursor){abort_if(in_array($cursor->id,$seen,true),422,'An account cannot be its own ancestor.');$seen[]=$cursor->id;$cursor=$cursor->parent_id?DB::table('accounting_accounts')->where('company_id',$company)->where('id',$cursor->parent_id)->first():null;}
   }
   abort_if(!$v['active']&&DB::table('accounting_accounts')->where('company_id',$company)->where('parent_id',$id)->where('active',true)->exists(),422,'Deactivate active children first, or use Delete account and children.');
   History::record($r,$company,'account',$old,'edit',$v['reason']);
   DB::table('accounting_accounts')->where('id',$id)->update(['name'=>$name,'code'=>$code,'description'=>$v['description']??null,'parent_id'=>$parent,'active'=>$v['active'],'version'=>$old->version+1,'updated_at'=>now()]);
   $party=DB::table('accounting_parties')->where('company_id',$company)->where('account_id',$id)->first();
   if($party)DB::table('accounting_parties')->where('id',$party->id)->update(['name'=>$name,'active'=>$v['active'],'version'=>$party->version+1,'updated_at'=>now()]);
   $fund=DB::table('accounting_imprest_funds')->where('company_id',$company)->where('account_id',$id)->first();if($fund)DB::table('accounting_imprest_funds')->where('id',$fund->id)->update(['name'=>$name,'active'=>$v['active'],'updated_at'=>now()]);
   return ['message'=>'Account updated. Existing entries and financial classification are preserved.'];
  });
 }
 public function saveCheque(Request $r,int $id){
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can manage these records.');
  $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','invoice_id'=>'required|integer','bank_account_id'=>'required|integer','cheque_number'=>'required|string|max:100','drawee_bank'=>'required|string|max:150','cheque_date'=>'required|date_format:Y-m-d','recorded_date'=>'required|date_format:Y-m-d|before_or_equal:today','amount'=>'required','notes'=>'nullable|string|max:2000','reason'=>'required|string|min:5|max:2000']);
  $company=(int)$v['company_id'];$this->authorize($r,$company);
  return DB::transaction(function()use($r,$v,$id,$company){
   Ledger::lock($company);History::assertLive('cheque',$company,$id);
   $old=DB::table('accounting_cheques')->where('company_id',$company)->where('id',$id)->lockForUpdate()->first();abort_unless($old,404);abort_unless($old->version===(int)$v['version'],409,'Cheque changed. Reopen it.');
   abort_unless($v['recorded_date']===$old->recorded_date,422,'The original registration date is retained for history.');
   $this->unwindCheque($r,$old,now()->toDateString(),$v['reason']);
   $invoice=DB::table('accounting_invoices')->where('company_id',$company)->where('id',$v['invoice_id'])->where('status','posted')->first();abort_unless($invoice,422,'Choose a posted invoice in this company.');History::assertLive('invoice',$company,$invoice->id);
   $book=Ledger::book($company);abort_unless(($invoice->currency??$book->currency)===$book->currency,422,'Choose a base-currency invoice.');
   $bank=TradeDocuments::account($company,$v['bank_account_id'],['Bank'],['Asset']);abort_if($bank->currency&&$bank->currency!==$book->currency,422,'Choose a base-currency bank account.');
   abort_if($v['cheque_date']<$v['recorded_date']||$v['recorded_date']<$invoice->posting_date,422,'Cheque dates cannot precede registration or the invoice.');
   $paid=(int)DB::table('accounting_payments')->where('invoice_id',$invoice->id)->where('status','posted')->sum('amount_minor');
   $reserved=(int)DB::table('accounting_cheques')->where('invoice_id',$invoice->id)->where('id','<>',$id)->whereIn('status',['pending','deposited','clearing'])->sum('amount_minor');$amount=Ledger::minor($v['amount']);
   abort_unless($amount>0&&$amount<=$invoice->total_minor-$paid-$reserved,422,'Cheque exceeds the unreserved invoice outstanding.');
   $direction=$invoice->kind==='sale'?'received':'issued';
   abort_if(DB::table('accounting_cheques')->where(['company_id'=>$company,'direction'=>$direction,'drawee_bank'=>trim($v['drawee_bank']),'cheque_number'=>trim($v['cheque_number'])])->where('id','<>',$id)->exists(),422,'This cheque number already exists for that bank.');
   History::record($r,$company,'cheque',$old,'edit',$v['reason']);
   DB::table('accounting_cheques')->where('id',$id)->update(['invoice_id'=>$invoice->id,'party_id'=>$invoice->party_id,'bank_account_id'=>$bank->id,'cheque_number'=>trim($v['cheque_number']),'drawee_bank'=>trim($v['drawee_bank']),'cheque_date'=>$v['cheque_date'],'amount_minor'=>$amount,'notes'=>$v['notes']??null,'direction'=>$direction,'status'=>'pending','cleared_date'=>null,'payment_key'=>(string)Str::uuid(),'version'=>$old->version+1,'updated_at'=>now()]);
   $this->chequeEvent($r,$id,'admin_edited',now()->toDateString(),$v['reason']);
   return ['message'=>'Cheque corrected. Previous cleared amounts were reversed; the corrected cheque is pending clearance.'];
  });
 }
 public function replacePayment(Request $r,int $invoiceId){
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can replace payments.');
  $v=$r->validate(['company_id'=>'required|integer','replacement_payment_id'=>'required|integer','replacement_payment_version'=>'required|integer|min:1','reason'=>'required|string|min:5|max:2000','request_key'=>'required|uuid','posting_date'=>'required|date_format:Y-m-d']);$company=(int)$v['company_id'];$this->authorize($r,$company);
  return DB::transaction(function()use($r,$v,$company,$invoiceId){
   Ledger::lock($company);
   $existing=DB::table('accounting_payments')->where('company_id',$company)->where('request_key',$v['request_key'])->first();$pending=DB::table('accounting_payment_requests')->where('company_id',$company)->where('request_key',$v['request_key'])->first();
   if($existing||$pending){
    abort_unless(($existing??$pending)->invoice_id===$invoiceId,409,'This request key belongs to another invoice.');
    $payload=collect($r->all())->except(['replacement_payment_id','replacement_payment_version','reason'])->all();$child=Request::create('/accounting/api/invoices/'.$invoiceId.'/payments','POST',$payload);$child->setUserResolver($r->getUserResolver());if($r->hasSession())$child->setLaravelSession($r->session());$child->query->replace($r->query->all());return app(TradeController::class)->payment($child,$invoiceId);
   }
   $old=DB::table('accounting_payments')->where('company_id',$company)->where('invoice_id',$invoiceId)->where('id',$v['replacement_payment_id'])->lockForUpdate()->first();abort_unless($old,404);
   abort_unless($old->status==='posted'&&$old->version===(int)$v['replacement_payment_version'],409,'The original payment changed. Reopen it.');
   abort_if(DB::table('accounting_cheques')->where('company_id',$company)->where('payment_key',$old->request_key)->exists(),422,'Correct this payment through its PDC record.');
   $journal=DB::table('accounting_journals')->where('company_id',$company)->where('id',$old->journal_id)->first();
   Ledger::reverse($r,$company,$journal->id,['version'=>$journal->version,'posting_date'=>$v['posting_date'],'reason'=>$v['reason']]);
   DB::table('accounting_payments')->where('id',$old->id)->update(['status'=>'reversed','version'=>$old->version+1,'updated_at'=>now()]);
   $payload=collect($r->all())->except(['replacement_payment_id','replacement_payment_version','reason'])->all();
   $child=Request::create('/accounting/api/invoices/'.$invoiceId.'/payments','POST',$payload);$child->setUserResolver($r->getUserResolver());if($r->hasSession())$child->setLaravelSession($r->session());$child->query->replace($r->query->all());
   $result=app(TradeController::class)->payment($child,$invoiceId);History::record($r,$company,'payment',$old,'edit',$v['reason']);return $result;
  });
 }
 private function chequeEvent(Request $r,int $id,string $action,string $date,string $reason):void{DB::table('accounting_cheque_events')->insert(['cheque_id'=>$id,'action'=>$action,'event_date'=>$date,'note'=>$reason,'actor_id'=>$r->user()?->id,'created_at'=>now()]);}
 private function unwindCheque(Request $r,object $c,string $date,string $reason):void{
  abort_if($date<$c->recorded_date,422,'Correction date cannot precede registration.');
  $payment=DB::table('accounting_payments')->where('company_id',$c->company_id)->where('request_key',$c->payment_key)->where('status','posted')->first();
  if($payment){$journal=DB::table('accounting_journals')->where('company_id',$c->company_id)->where('id',$payment->journal_id)->first();Ledger::reverse($r,$c->company_id,$journal->id,['version'=>$journal->version,'posting_date'=>$date,'reason'=>$reason]);DB::table('accounting_payments')->where('id',$payment->id)->update(['status'=>'reversed','version'=>$payment->version+1,'updated_at'=>now()]);}
  $pending=DB::table('accounting_payment_requests')->where('company_id',$c->company_id)->where('request_key',$c->payment_key)->where('status','draft')->first();
  if($pending){DB::table('accounting_payment_requests')->where('id',$pending->id)->update(['status'=>'cancelled','updated_at'=>now()]);$a=DB::table('accounting_approvals')->where('company_id',$c->company_id)->where('document_type','payment')->where('document_id',$pending->id)->first();if($a){DB::table('accounting_approvals')->where('id',$a->id)->update(['stage'=>'withdrawn','version'=>$a->version+1,'updated_at'=>now()]);\App\Modules\Accounting\Services\ApprovalFlow::event($r,$a->id,'withdraw',$a->stage,'withdrawn',$reason);}}
 }
 public function removeOrder(Request $r,object $order,string $reason,string $date):void{
  foreach([$order->seller_company_id,$order->buyer_company_id] as $company){$this->authorize($r,$company);}
  foreach([$order->sales_invoice_id,$order->purchase_invoice_id] as $invoiceId){
   if(!$invoiceId)continue;$invoice=DB::table('accounting_invoices')->where('id',$invoiceId)->first();if(!$invoice)continue;
   if(History::removed('invoice',$invoice->company_id)->where('record_id',$invoice->id)->exists())continue;
   $child=Request::create('/accounting/api/admin-records/invoice/'.$invoiceId,'POST',['company_id'=>$invoice->company_id,'version'=>$invoice->version,'action'=>'delete','reason'=>$reason,'posting_date'=>$date]);$child->setUserResolver($r->getUserResolver());if($r->hasSession())$child->setLaravelSession($r->session());app(AdminRecordController::class)->action($child,'invoice',$invoiceId);
  }
  DB::table('accounting_intercompany_orders')->where('id',$order->id)->update(['status'=>'cancelled','version'=>$order->version+1,'updated_at'=>now()]);
  foreach([$order->seller_company_id,$order->buyer_company_id] as $company)History::record($r,$company,'order',$order,'delete',$reason);
 }
}
