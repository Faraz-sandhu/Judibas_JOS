<?php
namespace App\Modules\Accounting\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class CombinedAccounting
{
 private static function minor(string $value):int {$negative=str_starts_with($value,'-');$text=ltrim($value,'-');abort_unless(preg_match('/^(\d{1,16})\.(\d{2})$/D',$text,$m),422,'Unsupported report amount.');$n=(int)$m[1]*100+(int)$m[2];abort_unless(is_int($n),422,'Combined amount exceeds the supported range.');return $negative?-$n:$n;}
 private static function add(int $a,int $b):int {$n=$a+$b;abort_unless(is_int($n),422,'Combined amount exceeds the supported range.');return $n;}
 private static function report(Request $r,int $company,string $section,array $v,int $page=1):array {
  $request=Request::create('/accounting/combined','GET',['company_id'=>$company,'report'=>$section,'from'=>$v['from'],'to'=>$v['to'],'basis'=>$v['basis']??'accrual','trial_columns'=>$v['trial_columns']??'6','page'=>$page]);$request->setLaravelSession($r->session());$request->setUserResolver(fn()=>$r->user());
  return app(\App\Modules\Accounting\Http\Controllers\FinancialReportsController::class)->report($request);
 }
 private static function identity(object $account,array $accounts):string {
  $parts=[];$seen=[];$cursor=$account;
  while($cursor&&!isset($seen[$cursor->id])){$seen[$cursor->id]=true;array_unshift($parts,[$cursor->code,mb_strtolower(trim($cursor->name)),$cursor->root_type,$cursor->account_type,(bool)$cursor->is_group]);$cursor=$accounts[$cursor->parent_id]??null;}
  return json_encode($parts);
 }
 public static function build(Request $r,string $section,array $v):array {
  $companies=DB::table('communication_companies as c')->whereIn('c.id',\App\Services\CatalogueCompanyScope::ids('accounts',$r))->leftJoin('accounting_books as b','b.company_id','=','c.id')->when($v['search']??'',fn($q,$s)=>$q->where('c.name','ilike','%'.$s.'%'))->orderBy('c.name')->get(['c.id','c.name','b.currency','b.start_date','b.locked_through']);
  $rows=[];$groups=[];$unconfigured=0;$review=[];
  foreach($companies as $c){
   if(!$c->currency){$unconfigured++;if($section!=='books'&&$section!=='chart')continue;}
   $currency=$c->currency??'Not configured';
   $groups[$currency]??=['currency'=>$currency,'companies'=>0,'summary'=>[]];$groups[$currency]['companies']++;
   $url=\App\Services\CompanyWorkspace::url('accounting',$c->id).'?'.http_build_query(['from'=>$v['from'],'to'=>$v['to'],'basis'=>$v['basis']??'accrual','trial_columns'=>$v['trial_columns']??'6']).'#'.$section;
   if($section==='books'){$rows[]=(array)$c+['company_name'=>$c->name,'url'=>$url];continue;}
   $accounts=DB::table('accounting_accounts')->where('company_id',$c->id)->get()->keyBy('id')->all();
   if($section==='overview'){
    $companySummary=[];
    foreach(['profit','receivables','payables'] as $type){$report=self::report($r,$c->id,$type,$v);$companySummary[$type]=$report['summary'];foreach($report['summary'] as $key=>$value)$groups[$currency]['summary'][$type.'_'.$key]=self::add($groups[$currency]['summary'][$type.'_'.$key]??0,self::minor($value));}
    $cash=DB::table('accounting_journal_lines as l')->join('accounting_journals as j','j.id','=','l.journal_id')->join('accounting_accounts as a','a.id','=','l.account_id')->where('j.company_id',$c->id)->whereIn('j.status',['posted','reversed'])->where('j.posting_date','<=',$v['to'])->whereIn('a.account_type',['Cash','Bank'])->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as balance')->value('balance');
    $groups[$currency]['summary']['cash_bank']=self::add($groups[$currency]['summary']['cash_bank']??0,(int)$cash);
    $rows[]=['company_id'=>$c->id,'company_name'=>$c->name,'currency'=>$currency,'cash_bank'=>Ledger::money((int)$cash),'receivables'=>$companySummary['receivables']['outstanding'],'payables'=>$companySummary['payables']['outstanding'],'income'=>$companySummary['profit']['income'],'expenses'=>$companySummary['profit']['expense'],'url'=>$url];
    continue;
   }
   if($section==='chart'){
    $source=ChartSummary::accounts($c->id)->map(fn($a)=>(array)$a)->all();
    foreach($source as $a){$balance=self::minor($a['debit'])-self::minor($a['credit']);
     if(!$a['parent_id']){$key=strtolower($a['root_type']);$normal=in_array($a['root_type'],['Liability','Equity','Income'])?-$balance:$balance;$groups[$currency]['summary'][$key]=self::add($groups[$currency]['summary'][$key]??0,$normal);}
     if(!$a['is_group']&&in_array($a['account_type'],['Cash','Bank']))$groups[$currency]['summary']['cash_bank']=self::add($groups[$currency]['summary']['cash_bank']??0,$balance);
    }
   }
   else {
    $report=self::report($r,$c->id,$section,$v);
    foreach($report['summary']??array_intersect_key($report,array_flip(['opening','debit','credit','closing'])) as $key=>$value)$groups[$currency]['summary'][$key]=self::add($groups[$currency]['summary'][$key]??0,self::minor($value));
    foreach($report['cash_basis_review']??[] as $entry)$review[]=$entry+['company_id'=>$c->id,'company_name'=>$c->name,'currency'=>$currency,'url'=>$url];
    $source=isset($report['entries'])?$report['entries']->items():$report['rows'];
    $last=$report['last_page']??(isset($report['entries'])?$report['entries']->lastPage():1);
    for($p=2;$p<=$last;$p++){$more=self::report($r,$c->id,$section,$v,$p);$source=array_merge($source,isset($more['entries'])?$more['entries']->items():$more['rows']);}
   }
   $journals=$section==='ledger'?DB::table('accounting_journals')->where('company_id',$c->id)->get(['id','number','kind'])->keyBy('number'):collect();
   foreach($source as $entry){$entry=(array)$entry;
    $detail=$entry+['company_id'=>$c->id,'company_name'=>$c->name,'currency'=>$currency,'url'=>$url];
    if(in_array($section,['ledger','receivables','payables'])){
     $detail['url']=$url;
     if($section==='ledger'&&($journal=$journals->get($entry['number']))){$target=isset(VoucherTypes::TYPES[$journal->kind])?'single-vouchers':'journals';$detail['url']=\App\Services\CompanyWorkspace::url('accounting',$c->id).'?entry_id='.$journal->id.'#'.$target;}
     if(isset($entry['id'])&&$section!=='ledger')$detail['url']=\App\Services\CompanyWorkspace::url('accounting',$c->id).'?entry_id='.$entry['id'].'#'.($section==='receivables'?'sales':'purchases');
     $rows[]=$detail;continue;
    }
    $account=$accounts[$entry['account_id']??$entry['id']]??null;if(!$account)continue;
    $key=$currency.'|'.self::identity($account,$accounts);
    if(!isset($rows[$key]))$rows[$key]=['key'=>hash('sha256',$key),'parent_key'=>$account->parent_id&&isset($accounts[$account->parent_id])?hash('sha256',$currency.'|'.self::identity($accounts[$account->parent_id],$accounts)):null,'currency'=>$currency,'code'=>$account->code,'name'=>$account->name,'root_type'=>$account->root_type,'is_group'=>(bool)$account->is_group,'account_type'=>$account->account_type,'contributors'=>[],'minor'=>[]];
    $detail['url']=\App\Services\CompanyWorkspace::url('accounting',$c->id).'?'.($section==='chart'?'entry_id=':'account_id=').$account->id.'&from='.$v['from'].'&to='.$v['to'].'#'.($section==='chart'?'chart':'ledger');
    $rows[$key]['contributors'][]=$detail;
    foreach(['debit','credit','opening','amount'] as $field)if(isset($entry[$field]))$rows[$key]['minor'][$field]=self::add($rows[$key]['minor'][$field]??0,self::minor($entry[$field]));
   }
  }
  foreach($rows as &$row){if(isset($row['url']))$row['url']=\App\Services\CatalogueCompanyScope::link($row['url'],$r);if(isset($row['contributors']))foreach($row['contributors'] as &$contributor){if(isset($contributor['url']))$contributor['url']=\App\Services\CatalogueCompanyScope::link($contributor['url'],$r);}unset($contributor);}unset($row);
  foreach($rows as &$row){
   if(!isset($row['minor']))continue;$n=$row['minor'];$opening=$n['opening']??0;$balance=self::add(self::add($opening,$n['debit']??0),-($n['credit']??0));
   foreach($n as $key=>$value)$row[$key]=Ledger::money($value);
   $row+=['opening_debit'=>Ledger::money(max(0,$opening)),'opening_credit'=>Ledger::money(max(0,-$opening)),'closing_debit'=>Ledger::money(max(0,$balance)),'closing_credit'=>Ledger::money(max(0,-$balance)),'balance'=>Ledger::money(abs($balance)),'balance_side'=>$balance===0?'':($balance>0?'Dr':'Cr')];unset($row['minor']);
  }unset($row);
  if($section==='trial'){foreach($rows as $row)foreach(['opening_debit','opening_credit','closing_debit','closing_credit'] as $key)$groups[$row['currency']]['summary'][$key]=self::add($groups[$row['currency']]['summary'][$key]??0,self::minor($row[$key]));}
  foreach($groups as &$group)foreach($group['summary'] as &$amount)$amount=Ledger::money($amount);unset($group,$amount);
  $allRows=array_values($rows);if(in_array($section,['chart','trial','profit','balance']))usort($allRows,fn($a,$b)=>[$a['currency'],$a['code'],$a['name']]<=>[$b['currency'],$b['code'],$b['name']]);$page=(int)($v['page']??1);$completeView=in_array($section,['chart','profit','balance']);$perPage=$completeView?max(1,count($allRows)):20;if($completeView)$page=1;$records=new LengthAwarePaginator(array_slice($allRows,($page-1)*$perPage,$perPage),count($allRows),$perPage,$page,['path'=>$r->url()]);
  $statements=[];if(in_array($section,['profit','balance']))foreach($groups as $currency=>$group)$statements[]=['currency'=>$currency,'summary'=>$group['summary'],'from'=>$v['from'],'to'=>$v['to'],'basis'=>$v['basis']??'accrual','rows'=>array_values(array_map(fn($row)=>$row+['account_id'=>$row['key']],array_filter($allRows,fn($row)=>$row['currency']===$currency)))];
  return ['mode'=>'combined','records'=>$records,'totals'=>array_values($groups),'company_count'=>$companies->count(),'unconfigured_companies'=>$unconfigured,'cash_basis_review'=>$review,'statements'=>$statements,'from'=>$v['from'],'to'=>$v['to'],'basis'=>$v['basis']??'accrual'];
 }
}
