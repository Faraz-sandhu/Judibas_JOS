<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialReportsController
{
    public function report(Request $r)
    {
        $outer = DB::transactionLevel() === 0;

        return DB::transaction(function () use ($r, $outer) {
            if ($outer && DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $this->buildReport($r);
        });
    }

    private function buildReport(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'report' => 'required|in:trial,ledger,profit,balance,receivables,payables', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'account_id' => 'nullable|integer', 'party_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'export' => 'nullable|in:csv']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $book = Ledger::book($company);
        if (in_array($v['report'], ['receivables', 'payables'])) {
            return $this->aging($r, $company, $v, $book->currency);
        }
        $q = DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('j.company_id', $company)->whereIn('j.status', ['posted', 'reversed']);
        if (! empty($v['account_id'])) {
            abort_unless(DB::table('accounting_accounts')->where('company_id', $company)->where('id', $v['account_id'])->where('is_group', false)->exists(), 422, 'Select a ledger account in this company.');
        }
        $result = ['currency' => $book->currency, 'report' => $v['report'], 'from' => $v['from'], 'to' => $v['to']];
        if ($v['report'] === 'ledger') {
            if (! empty($v['party_id'])) {
                abort_unless(DB::table('accounting_parties')->where('company_id', $company)->where('id', $v['party_id'])->exists(), 422, 'Select a contact in this company.');
                $q->where('l.party_id', $v['party_id']);
            }
            if (! empty($v['account_id'])) {
                $q->where('l.account_id', $v['account_id']);
            }
            $opening = (int) (clone $q)->where('j.posting_date', '<', $v['from'])->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as amount')->value('amount');
            $period = (clone $q)->whereBetween('j.posting_date', [$v['from'], $v['to']]);
            $total = (clone $period)->selectRaw('COALESCE(SUM(l.debit_minor),0) as debit, COALESCE(SUM(l.credit_minor),0) as credit')->first();
            $query = $period->select(['l.id', 'j.posting_date', 'j.number', 'j.status', 'j.remarks', 'a.code', 'a.name', 'l.debit_minor', 'l.credit_minor'])->selectRaw('? + SUM(l.debit_minor-l.credit_minor) OVER (ORDER BY j.posting_date,j.id,l.position ROWS UNBOUNDED PRECEDING) as balance_minor', [$opening])->orderBy('j.posting_date')->orderBy('j.id')->orderBy('l.position');
            $result += ['opening' => Ledger::money($opening), 'debit' => Ledger::money((int) $total->debit), 'credit' => Ledger::money((int) $total->credit), 'closing' => Ledger::money($opening + (int) $total->debit - (int) $total->credit)];
            if ($r->input('export') === 'csv') {
                return $this->csv($r, $company, $v['report'], ['Date', 'Journal', 'Account code', 'Account', 'Description', 'Debit', 'Credit', 'Running debit balance'], $query->get()->map(fn ($l) => [$l->posting_date, $l->number, $l->code, $l->name, $l->remarks, Ledger::money($l->debit_minor), Ledger::money($l->credit_minor), Ledger::money($l->balance_minor)]));
            }
            $rows = $query->paginate(30);
            $rows->setCollection($rows->getCollection()->map(fn ($l) => array_merge((array) $l, ['debit' => Ledger::money($l->debit_minor), 'credit' => Ledger::money($l->credit_minor), 'balance' => Ledger::money($l->balance_minor)])));

            return $result + ['entries' => $rows];
        }
        $totals = (clone $q)->where('j.posting_date', '<=', $v['to'])->select(['l.account_id'])->selectRaw('SUM(CASE WHEN j.posting_date < ? THEN l.debit_minor-l.credit_minor ELSE 0 END) as opening, SUM(CASE WHEN j.posting_date >= ? THEN l.debit_minor ELSE 0 END) as debit, SUM(CASE WHEN j.posting_date >= ? THEN l.credit_minor ELSE 0 END) as credit, SUM(l.debit_minor-l.credit_minor) as balance', [$v['from'], $v['from'], $v['from']])->groupBy('l.account_id')->get()->keyBy('account_id');
        $accounts = DB::table('accounting_accounts')->where('company_id', $company)->where('is_group', false)->orderBy('code')->get();
        $rows = [];
        $sumOpening = $sumDebit = $sumCredit = $sumClosing = $income = $expense = $assets = $liabilities = $equity = $earnings = 0;
        foreach ($accounts as $a) {
            $t = $totals->get($a->id);
            $opening = (int) ($t->opening ?? 0);
            $debit = (int) ($t->debit ?? 0);
            $credit = (int) ($t->credit ?? 0);
            $balance = (int) ($t->balance ?? 0);
            $sumOpening += $opening;
            $sumDebit += $debit;
            $sumCredit += $credit;
            $sumClosing += $balance;
            if ($a->root_type === 'Income') {
                $income += $credit - $debit;
                $earnings -= $balance;
            } elseif ($a->root_type === 'Expense') {
                $expense += $debit - $credit;
                $earnings -= $balance;
            } elseif ($a->root_type === 'Asset') {
                $assets += $balance;
            } elseif ($a->root_type === 'Liability') {
                $liabilities -= $balance;
            } elseif ($a->root_type === 'Equity') {
                $equity -= $balance;
            }
            if ($v['report'] === 'profit' && ! in_array($a->root_type, ['Income', 'Expense'])) {
                continue;
            }
            if ($v['report'] === 'balance' && in_array($a->root_type, ['Income', 'Expense'])) {
                continue;
            }
            $amount = $v['report'] === 'profit' ? ($a->root_type === 'Income' ? $credit - $debit : $debit - $credit) : (in_array($a->root_type, ['Liability', 'Equity']) ? -$balance : $balance);
            $rows[] = ['account_id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'root_type' => $a->root_type, 'opening' => Ledger::money($opening), 'debit' => Ledger::money($debit), 'credit' => Ledger::money($credit), 'closing_debit' => Ledger::money(max(0, $balance)), 'closing_credit' => Ledger::money(max(0, -$balance)), 'amount' => Ledger::money($amount)];
        }
        $result += ['rows' => $rows, 'summary' => ['opening_difference' => Ledger::money($sumOpening), 'debit' => Ledger::money($sumDebit), 'credit' => Ledger::money($sumCredit), 'closing_difference' => Ledger::money($sumClosing), 'income' => Ledger::money($income), 'expense' => Ledger::money($expense), 'profit' => Ledger::money($income - $expense), 'assets' => Ledger::money($assets), 'liabilities' => Ledger::money($liabilities), 'equity' => Ledger::money($equity), 'accumulated_earnings' => Ledger::money($earnings), 'balance_difference' => Ledger::money($assets - $liabilities - $equity - $earnings)]];
        if ($r->input('export') === 'csv') {
            return $this->csv($r, $company, $v['report'], $v['report'] === 'trial' ? ['Account code', 'Account', 'Type', 'Opening (debit positive)', 'Period debit', 'Period credit', 'Closing debit', 'Closing credit'] : ['Account code', 'Account', 'Type', 'Amount'], collect($rows)->map(fn ($l) => $v['report'] === 'trial' ? [$l['code'], $l['name'], $l['root_type'], $l['opening'], $l['debit'], $l['credit'], $l['closing_debit'], $l['closing_credit']] : [$l['code'], $l['name'], $l['root_type'], $l['amount']])->when($v['report'] === 'balance', fn ($rows) => $rows->push(['', 'Accumulated earnings', 'Equity', Ledger::money($earnings)])));
        }

        return $result;
    }

    private function csv(Request $r, int $company, string $report, array $headings, $rows)
    {
        Access::audit($r, $company, 'Report exported', $report);

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) && ! preg_match('/^-?\d+(?:\.\d+)?$/D', $value) ? "'".$value : $value, $row));
            }
            fclose($out);
        }, 'accounting-'.$report.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function aging(Request $r, int $company, array $v, string $currency)
    {
        // Historical balances use reversal dates, rather than today's document status.
        $paid = DB::table('accounting_payments as p')->join('accounting_journals as pj', 'pj.id', '=', 'p.journal_id')->leftJoin('accounting_journals as pr', 'pr.reversal_of', '=', 'pj.id')->where('p.company_id', $company)->where('p.posting_date', '<=', $v['to'])->where(fn ($q) => $q->whereNull('pr.id')->orWhere('pr.posting_date', '>', $v['to']))->selectRaw('p.invoice_id,SUM(p.amount_minor) as paid')->groupBy('p.invoice_id');
        $invoices = DB::table('accounting_invoices as i')->join('accounting_journals as j', 'j.id', '=', 'i.journal_id')->leftJoin('accounting_journals as r', 'r.reversal_of', '=', 'j.id')->leftJoinSub($paid, 'payments', 'payments.invoice_id', '=', 'i.id')->where('i.company_id', $company)->where('i.kind', $v['report'] === 'receivables' ? 'sale' : 'purchase')->where('i.posting_date', '<=', $v['to'])->where(fn ($q) => $q->whereNull('r.id')->orWhere('r.posting_date', '>', $v['to']))->select(['i.id', 'i.number', 'i.party_name', 'i.posting_date', 'i.due_date', 'i.total_minor'])->selectRaw('COALESCE(payments.paid,0) as paid')->orderBy('i.due_date')->orderBy('i.id')->get();
        $totals = array_fill_keys(['outstanding', 'current', 'days_1_30', 'days_31_60', 'days_61_90', 'over_90'], 0);
        $rows = [];
        foreach ($invoices as $i) {
            $outstanding = $i->total_minor - (int) $i->paid;
            if ($outstanding <= 0) continue;
            $days = max(0, (int) \Carbon\Carbon::parse($i->due_date)->diffInDays(\Carbon\Carbon::parse($v['to']), false));
            $bucket = $days === 0 ? 'current' : ($days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : ($days <= 90 ? 'days_61_90' : 'over_90')));
            $totals['outstanding'] += $outstanding;$totals[$bucket] += $outstanding;
            $rows[] = ['id' => $i->id, 'number' => $i->number, 'party_name' => $i->party_name, 'posting_date' => $i->posting_date, 'due_date' => $i->due_date, 'overdue_days' => $days, 'total' => Ledger::money($i->total_minor), 'paid' => Ledger::money((int) $i->paid), 'outstanding' => Ledger::money($outstanding), 'bucket' => $bucket];
        }
        if ($r->input('export') === 'csv') {
            return $this->csv($r, $company, $v['report'], ['Invoice', 'Contact', 'Date', 'Due date', 'Overdue days', 'Invoice total', 'Paid', 'Outstanding'], collect($rows)->map(fn ($i) => [$i['number'], $i['party_name'], $i['posting_date'], $i['due_date'], $i['overdue_days'], $i['total'], $i['paid'], $i['outstanding']]));
        }
        $page = (int) ($v['page'] ?? 1);

        return ['currency' => $currency, 'report' => $v['report'], 'to' => $v['to'], 'summary' => array_map(fn ($n) => Ledger::money($n), $totals), 'rows' => array_slice($rows, ($page - 1) * 30, 30), 'total' => count($rows), 'last_page' => max(1, (int) ceil(count($rows) / 30))];
    }
}
