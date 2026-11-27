<?php
namespace App\Modules\Accounting\Services;
use Illuminate\Support\Facades\{DB,Storage};
class PaymentProofs {
 public static function required(object $journal):bool{
  if(in_array($journal->kind,['income','expense','reversal'],true))return false;
  if(in_array($journal->kind,['general','opening'],true)&&!ImprestFunds::movement($journal->id))return false;
  return !DB::table('accounting_invoices')->where('company_id',$journal->company_id)->where('journal_id',$journal->id)->exists()&&!DB::table('accounting_payments')->where('company_id',$journal->company_id)->where('journal_id',$journal->id)->exists();
 }
 public static function fingerprint(object $journal):string{
  $fields=['company_id','id','request_key','kind','posting_date','currency','exchange_rate','voucher_person','reference','remarks'];$values=[];foreach($fields as $field)$values[$field]=$journal->$field??null;
  $values['lines']=DB::table('accounting_journal_lines')->where('company_id',$journal->company_id)->where('journal_id',$journal->id)->orderBy('position')->get(['position','account_id','party_id','debit_minor','credit_minor','description','currency','exchange_rate','foreign_debit_minor','foreign_credit_minor'])->map(fn($line)=>(array)$line)->all();
  return hash('sha256',json_encode($values));
 }
 public static function current(object $journal):?object{
  return DB::table('accounting_payment_proofs')->where('company_id',$journal->company_id)->where('journal_id',$journal->id)->where('document_hash',self::fingerprint($journal))->whereNull('removed_at')->orderByDesc('id')->get()->first(fn($proof)=>Storage::disk('local')->exists($proof->path));
 }
 public static function requireCurrent(object $journal):void{
  if(self::required($journal))abort_unless(self::current($journal),422,'Attach a payment proof for the current saved voucher before forwarding or posting. JV, opening, Income and Expense voucher types are exempt.');
 }
}
