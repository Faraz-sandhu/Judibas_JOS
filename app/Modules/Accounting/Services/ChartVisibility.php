<?php

namespace App\Modules\Accounting\Services;

use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChartVisibility
{
    public static function present(Request $request, int $company, Collection $accounts): Collection
    {
        $actual=PlatformAccess::super($request);
        $archived=AdminRecordHistory::removed('account',$company)->pluck('record_id')->all();
        $funds=DB::table('accounting_imprest_funds')->where('company_id',$company)
            ->when(ImprestFunds::restricted($request),fn($q)=>$q->where('user_id',$request->user()?->id))->get();
        // Allocated face amounts, not arbitrary account balances or pending drafts.
        $allocations=DB::table('accounting_imprest_movements as m')
            ->join('accounting_imprest_funds as f','f.id','=','m.fund_id')
            ->join('accounting_journals as j','j.id','=','m.journal_id')
            ->join('accounting_journal_lines as l','l.journal_id','=','j.id')
            ->join('accounting_accounts as a','a.id','=','l.account_id')
            ->whereIn('f.id',$funds->pluck('id'))->where('j.company_id',$company)->where('l.company_id',$company)->where('a.company_id',$company)
            ->where('m.type','funding')->where('m.void',false)->where('j.status','posted')->where('j.posting_date','<=',now()->toDateString())
            ->whereIn('a.account_type',['Cash','Bank'])->where('l.credit_minor','>',0)
            ->select('l.account_id','f.account_id as fund_account_id')->selectRaw('SUM(l.credit_minor) as amount')->groupBy('l.account_id','f.account_id')->get();
        $amounts=[];
        foreach($allocations as $allocation){
            $id=$allocation->account_id;$amounts[$id]=($amounts[$id]??0)+(int)$allocation->amount;
        }
        $byId=$accounts->keyBy('id');
        $related=function($account)use($byId){
            $seen=[];
            while($account&&!isset($seen[$account->id])){
                $seen[$account->id]=true;
                if(in_array($account->account_type,['Cash','Bank'],true))return true;
                $account=$byId->get($account->parent_id);
            }
            return false;
        };
        $cashBank=[];
        foreach($accounts as $account)$cashBank[$account->id]=$related($account);
        // Include dedicated Cash/Bank groups, but not mixed Assets/Receivables groups.
        do {
        $changed=false;
        foreach($accounts->where('is_group',true) as $group){
            if($cashBank[$group->id])continue;
            $children=$accounts->filter(fn($a)=>$a->parent_id===$group->id);
            if($children->isNotEmpty()&&$children->every(fn($a)=>$cashBank[$a->id]??false)){
                $cashBank[$group->id]=true;$changed=true;
            }
        }
        } while($changed);
        $displayAmounts=$amounts;
        foreach($amounts as $id=>$value){
            $cursor=$byId->get($id);$seen=[];
            while($cursor&&$cursor->parent_id&&!isset($seen[$cursor->parent_id])){
                $seen[$cursor->parent_id]=true;$cursor=$byId->get($cursor->parent_id);
                if($cursor&&($cashBank[$cursor->id]??false))$displayAmounts[$cursor->id]=($displayAmounts[$cursor->id]??0)+$value;
            }
        }
        $restricted=ImprestFunds::restricted($request);
        return $accounts->map(function($account)use($actual,$displayAmounts,$cashBank,$restricted,$archived){
            $account->archived=in_array($account->id,$archived,true);
            $allocation=$displayAmounts[$account->id]??0;
            $eligible=$cashBank[$account->id]??false;
            $assigned=!$actual&&$eligible&&($allocation>0||$restricted);
            $visible=$actual||$eligible;
            $account->actual_balance_visible=$actual||($eligible&&!$assigned);
            $account->display_balance_visible=$visible;
            $account->balance_basis=$assigned?'disbursement':($visible?'ledger':'hidden');
            $account->disbursement_amount=Ledger::money($allocation);
            if(!$actual){
                $account->debit=$account->credit='0.00';
                if($assigned){$account->balance=Ledger::money($allocation);$account->balance_side=$allocation?'Dr':'';}
                elseif(!$eligible){$account->balance='0.00';$account->balance_side='';}
                if(isset($account->foreign_balance)&&!$account->actual_balance_visible)$account->foreign_balance='0.00';
            }
            return $account;
        });
    }
}
