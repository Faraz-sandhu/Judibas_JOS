<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class CashFlow
{
    private static function category(object $a): string
    {
        if ($a->root_type === 'Equity') {
            return 'financing';
        }
        if ($a->account_type === 'Fixed Asset') {
            return 'investing';
        }
        if (in_array($a->account_type, ['Receivable', 'Payable', 'Inventory', 'Tax']) || in_array($a->root_type, ['Income', 'Expense'])) {
            return 'operating';
        }

        return 'review';
    }

    public static function report(int $company, string $from, string $to, string $currency): array
    {
        $q = DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('l.company_id', $company)->where('j.company_id', $company)->where('a.company_id', $company)->whereIn('j.status', ['posted', 'reversed'])->where('a.root_type', 'Asset')->whereIn('a.account_type', ['Cash', 'Bank']);
        $opening = (int) (clone $q)->where('j.posting_date', '<', $from)->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as amount')->value('amount');
        $movements = (clone $q)->whereBetween('j.posting_date', [$from, $to])->select('j.id', 'j.number', 'j.posting_date', 'j.remarks', 'j.reversal_of', 'j.kind')->selectRaw('SUM(l.debit_minor-l.credit_minor) as movement, SUM(l.debit_minor) as cash_debit, SUM(l.credit_minor) as cash_credit')->groupBy('j.id', 'j.number', 'j.posting_date', 'j.remarks', 'j.reversal_of', 'j.kind')->orderBy('j.posting_date')->orderBy('j.id')->get();
        $counterparts = DB::table('accounting_journal_lines as l')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('l.company_id', $company)->whereIn('l.journal_id', $movements->pluck('id'))->whereNotIn('a.account_type', ['Cash', 'Bank'])->get(['l.journal_id', 'a.root_type', 'a.account_type'])->groupBy('journal_id');
        $payments = DB::table('accounting_payments as p')->join('accounting_invoices as i', 'i.id', '=', 'p.invoice_id')->where('p.company_id', $company)->where('i.company_id', $company)->whereIn('p.journal_id', $movements->map(fn ($j) => $j->reversal_of ?? $j->id))->get(['p.journal_id', 'i.kind', 'i.lines', 'i.tax_minor'])->keyBy('journal_id');
        $allAccounts = DB::table('accounting_accounts')->where('company_id', $company)->get()->keyBy('id');
        $buckets = array_fill_keys(['operating', 'investing', 'financing', 'review'], 0);
        $inflow = $outflow = $transfers = $adjustments = 0;
        $rows = [];
        foreach ($movements as $j) {
            $n = (int) $j->movement;
            if ($j->kind === 'opening') {
                $adjustments += $n;

                continue;
            }
            $accounts = $counterparts->get($j->id, collect());
            if ($n === 0 && $accounts->isEmpty()) {
                $transfers++;

                continue;
            }
            // A supplier payment may settle a fixed-asset invoice. Use its original expense/asset lines.
            $payment = $payments->get($j->reversal_of ?? $j->id);
            $taxedAsset = false;
            if ($payment && $payment->kind === 'purchase') {
                $accounts = collect(json_decode($payment->lines, true))->map(fn ($l) => $allAccounts->get($l['account_id']))->filter();
                $taxedAsset = $payment->tax_minor > 0 && $accounts->contains(fn ($a) => self::category($a) === 'investing');
            }
            $categories = $accounts->map(fn ($a) => self::category($a))->unique();
            $mixedCash = (int) $j->cash_debit > 0 && (int) $j->cash_credit > 0;
            $category = $categories->count() === 1 && ! $mixedCash && ! $taxedAsset ? $categories->first() : 'review';
            $in = $mixedCash ? (int) $j->cash_debit : max(0, $n);
            $out = $mixedCash ? (int) $j->cash_credit : max(0, -$n);
            $buckets[$category] += $n;
            $inflow += $in;
            $outflow += $out;
            $rows[] = ['id' => $j->id, 'number' => $j->number, 'date' => $j->posting_date, 'description' => $j->remarks, 'category' => $category, 'inflow' => Ledger::money($in), 'outflow' => Ledger::money($out), 'net' => Ledger::money($n)];
        }
        $net = $inflow - $outflow;
        $balances = (clone $q)->where('j.posting_date', '<=', $to)->select('a.id', 'a.code', 'a.name', 'a.account_type')->selectRaw('SUM(l.debit_minor-l.credit_minor) as balance')->groupBy('a.id', 'a.code', 'a.name', 'a.account_type')->orderBy('a.code')->get()->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->account_type, 'balance' => Ledger::money((int) $a->balance)]);

        return ['report' => 'cashflow', 'currency' => $currency, 'from' => $from, 'to' => $to, 'summary' => ['opening' => Ledger::money($opening), 'inflow' => Ledger::money($inflow), 'outflow' => Ledger::money($outflow), 'net' => Ledger::money($net), 'opening_adjustments' => Ledger::money($adjustments), 'closing' => Ledger::money($opening + $net + $adjustments)], 'activities' => array_map(fn ($n) => Ledger::money($n), $buckets), 'review_count' => count(array_filter($rows, fn ($r) => $r['category'] === 'review')), 'internal_transfers_excluded' => $transfers, 'accounts' => $balances, 'rows' => $rows];
    }
}
