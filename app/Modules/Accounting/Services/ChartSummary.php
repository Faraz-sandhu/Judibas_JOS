<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChartSummary
{
    // Called while the company row is locked by the account controller.
    public static function nextCode(int $company, object $parent): string
    {
        $codes = DB::table('accounting_accounts')->where('company_id', $company)->pluck('code');
        $next = 1;
        foreach ($codes as $code) {
            if (preg_match('/^'.preg_quote($parent->code, '/').'-(\d+)$/D', $code, $match)) {
                $next = max($next, (int) $match[1] + 1);
            }
        }
        $code = $parent->code.'-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
        abort_if(strlen($code) > 255, 422, 'This group has reached the account code length limit. Choose a higher parent group.');

        return $code;
    }

    public static function accounts(int $company): Collection
    {
        $accounts = DB::table('accounting_accounts')->where('company_id', $company)->orderBy('code')->get();
        $totals = DB::table('accounting_journal_lines as l')
            ->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('j.company_id', $company)->where('l.company_id', $company)
            ->whereIn('j.status', ['posted', 'reversed'])->where('j.posting_date', '<=', now()->toDateString())
            ->groupBy('l.account_id')->selectRaw('l.account_id, SUM(l.debit_minor) as debit, SUM(l.credit_minor) as credit, SUM(COALESCE(l.foreign_debit_minor,0)-COALESCE(l.foreign_credit_minor,0)) as foreign_balance, MAX(j.created_at) as last_entry_at')->get()->keyBy('account_id');
        $byId = $accounts->keyBy('id');
        $sums = [];
        $lastEntries = [];
        foreach ($accounts as $account) {
            if ($account->is_group) {
                continue;
            }
            $total = $totals->get($account->id);
            $cursor = $account;
            $seen = [];
            while ($cursor && ! isset($seen[$cursor->id])) {
                $seen[$cursor->id] = true;
                if($total?->last_entry_at && (!isset($lastEntries[$cursor->id])||$total->last_entry_at>$lastEntries[$cursor->id]))$lastEntries[$cursor->id]=$total->last_entry_at;
                $sums[$cursor->id][0] = ($sums[$cursor->id][0] ?? 0) + (int) ($total->debit ?? 0);
                $sums[$cursor->id][1] = ($sums[$cursor->id][1] ?? 0) + (int) ($total->credit ?? 0);
                $cursor = $byId->get($cursor->parent_id);
            }
        }
        return $accounts->map(function ($account) use ($sums,$totals,$lastEntries) {
            [$debit, $credit] = $sums[$account->id] ?? [0, 0];
            $balance = $debit - $credit;
            if($account->currency)$account->foreign_balance=Ledger::money((int)($totals->get($account->id)->foreign_balance??0));
            $account->last_entry_at=$lastEntries[$account->id]??null;
            foreach(['created_at','updated_at','last_entry_at'] as $field){if(!empty($account->$field))$account->$field=\Carbon\Carbon::parse($account->$field,config('app.timezone'))->toIso8601String();}
            $account->debit = Ledger::money($debit);
            $account->credit = Ledger::money($credit);
            $account->balance = Ledger::money(abs($balance));
            $account->balance_side = $balance === 0 ? '' : ($balance > 0 ? 'Dr' : 'Cr');

            return $account;
        });
    }
}
