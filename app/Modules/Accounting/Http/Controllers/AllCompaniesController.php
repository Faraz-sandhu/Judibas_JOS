<?php
namespace App\Modules\Accounting\Http\Controllers;
use App\Modules\Accounting\Services\{AccountingAccess as Access,Permissions,Ledger};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AllCompaniesController
{
 public static function authorize(Request $r):void {Access::role($r);abort_unless(PlatformAccess::administrator($r),403,'All companies is available to platform administrators only.');}
 public function index(Request $r) {
  self::authorize($r);$outer=DB::transactionLevel()===0;
  return DB::transaction(function()use($r,$outer){if($outer&&DB::getDriverName()==='pgsql')DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');return $this->build($r);});
 }
 private function financial(Request $r,int $company,string $section,array $v):array {
  $request=Request::create('/accounting/all-report','GET',['company_id'=>$company,'report'=>$section,'from'=>$v['from'],'to'=>$v['to'],'basis'=>$v['basis']??'accrual','trial_columns'=>$v['trial_columns']??'6']);$request->setLaravelSession($r->session());$request->setUserResolver(fn()=>$r->user());
  return app(FinancialReportsController::class)->report($request);
 }
 private function build(Request $r) {
  self::authorize($r);
  $v=$r->validate(['section'=>'required|in:overview,customers,suppliers,sales,purchases,journals,single-vouchers,cash-receipts,cash-payments,chart,ledger,trial,profit,balance,receivables,payables,books,audit,approvals','page'=>'nullable|integer|min:1','search'=>'nullable|string|max:150','from'=>'required|date_format:Y-m-d','to'=>'required|date_format:Y-m-d|after_or_equal:from','status'=>'nullable|in:draft,posted,reversed','basis'=>'nullable|in:cash,accrual','trial_columns'=>'nullable|in:2,6']);
  $section=$v['section'];$search=$v['search']??'';
  $permission=['overview'=>'overview.view','customers'=>'customers.view','suppliers'=>'suppliers.view','sales'=>'sales.view','purchases'=>'purchases.view','journals'=>'journals.view','single-vouchers'=>'journals.view','cash-receipts'=>'cash_receipts.view','cash-payments'=>'cash_payments.view','chart'=>'chart.view','books'=>'books.view','audit'=>'audit.view','approvals'=>'workflow.view'][$section]??'reports.'.$section;
  Permissions::require($r,$permission);
  if(in_array($section,['overview','books','chart','ledger','trial','profit','balance','receivables','payables']))return \App\Modules\Accounting\Services\CombinedAccounting::build($r,$section,$v);
  $table=match($section){'customers','suppliers'=>'accounting_parties','sales','purchases'=>'accounting_invoices','audit'=>'accounting_audits','approvals'=>'accounting_approvals',default=>'accounting_journals'};
  $q=DB::table($table.' as x')->whereIn('x.company_id',\App\Services\CatalogueCompanyScope::ids('accounts',$r))->join('communication_companies as c','c.id','=','x.company_id')->leftJoin('accounting_books as b','b.company_id','=','c.id');
  if(in_array($section,['customers','suppliers']))$q->where('x.kind',$section==='customers'?'customer':'supplier');
  elseif(in_array($section,['sales','purchases']))$q->where('x.kind',$section==='sales'?'sale':'purchase');
  elseif($table==='accounting_journals'){
   $q->whereIn('x.kind',match($section){'journals'=>['general','opening','reversal'],'cash-receipts'=>['cash_receipt'],'cash-payments'=>['cash_payment'],default=>array_keys(\App\Modules\Accounting\Services\VoucherTypes::TYPES)});
  }
  if(in_array($table,['accounting_journals','accounting_invoices']))$q->whereBetween('x.posting_date',[$v['from'],$v['to']])->when($v['status']??null,fn($q,$s)=>$q->where('x.status',$s));
  if($search)$q->where(function($q)use($search,$table){$q->where('c.name','ilike','%'.$search.'%');$field=match($table){'accounting_parties'=>'name','accounting_audits'=>'subject','accounting_approvals'=>'stage',default=>'number'};$q->orWhere('x.'.$field,'ilike','%'.$search.'%');});
  $columns=match($table){'accounting_parties'=>['x.id','x.company_id','x.name','x.email','x.active','x.kind'], 'accounting_audits'=>['x.id','x.company_id','x.actor_name','x.action','x.subject','x.created_at'], 'accounting_approvals'=>['x.id','x.company_id','x.document_type','x.document_id','x.stage','x.created_at'], 'accounting_invoices'=>['x.id','x.company_id','x.number','x.kind','x.party_name','x.status','x.total_minor','x.posting_date'], default=>['x.id','x.company_id','x.number','x.kind','x.status','x.voucher_person','x.remarks','x.posting_date']};
  $totals=[];
  if(in_array($table,['accounting_journals','accounting_invoices'])){
   $totalsQuery=clone $q;
   if($table==='accounting_journals'){$lines=DB::table('accounting_journal_lines')->selectRaw('journal_id, SUM(debit_minor) as document_amount')->groupBy('journal_id');$totalsQuery->leftJoinSub($lines,'sums','sums.journal_id','=','x.id');$amount='COALESCE(sums.document_amount,0)';}else $amount='x.total_minor';
   $totals=$totalsQuery->selectRaw("b.currency, COUNT(*) as documents, COALESCE(SUM(CASE WHEN x.status='posted' THEN {$amount} ELSE 0 END),0) as posted_amount, COALESCE(SUM(CASE WHEN x.status='draft' THEN {$amount} ELSE 0 END),0) as draft_amount, COALESCE(SUM(CASE WHEN x.status='reversed' THEN {$amount} ELSE 0 END),0) as reversed_amount")->groupBy('b.currency')->orderBy('b.currency')->get()->map(function($g){foreach(['posted_amount','draft_amount','reversed_amount'] as $key)$g->$key=Ledger::money((int)$g->$key);return $g;});
  }
  $rows=$q->select(array_merge($columns,['c.name as company_name','b.currency']))->orderByDesc('x.id')->paginate(20);
  $rows->setCollection($rows->getCollection()->map(function($row)use($section,$table,$r){
   $target=$table==='accounting_journals'&&isset(\App\Modules\Accounting\Services\VoucherTypes::TYPES[$row->kind])?'single-vouchers':$section;
   $row->url=\App\Services\CatalogueCompanyScope::link(\App\Services\CompanyWorkspace::url('accounting',$row->company_id).'?entry_id='.$row->id.'#'.$target,$r);
   if($table==='accounting_invoices')$row->amount=Ledger::money($row->total_minor);
   if($table==='accounting_journals')$row->amount=Ledger::money((int)DB::table('accounting_journal_lines')->where('journal_id',$row->id)->sum('debit_minor'));
   return $row;
  }));return ['mode'=>'records','records'=>$rows,'totals'=>$totals];
 }
}
