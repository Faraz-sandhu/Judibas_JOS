<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class CashBasisProfit
{
    public static function report(int $company, string $from, string $to, string $currency): array
    {
        $accounts = DB::table('accounting_accounts')->where('company_id', $company)->get()->keyBy('id');
        $amounts = [];
        $add = function (int $account, int $amount) use (&$amounts, $accounts) {
            if (in_array($accounts[$account]->root_type, ['Income', 'Expense'])) {
                $amounts[$account] = ($amounts[$account] ?? 0) + $amount;
            }
        };

        // Include historical payments even if reversed today; recognise the offset on its own date.
        $payments = DB::table('accounting_payments as p')
            ->join('accounting_invoices as i', 'i.id', '=', 'p.invoice_id')
            ->join('accounting_journals as j', 'j.id', '=', 'p.journal_id')
            ->leftJoin('accounting_journals as r', 'r.reversal_of', '=', 'j.id')
            ->where('p.company_id', $company)->where('i.company_id', $company)->where('j.company_id', $company)
            ->whereIn('j.status', ['posted', 'reversed'])->where('p.posting_date', '<=', $to)
            ->get(['p.id', 'p.invoice_id', 'p.amount_minor', 'p.posting_date', 'p.journal_id', 'i.journal_id as invoice_journal', 'i.control_account_id', 'r.id as reversal_id', 'r.posting_date as reversal_date']);
        $components = DB::table('accounting_journal_lines')->where('company_id', $company)
            ->whereIn('journal_id', $payments->pluck('invoice_journal')->unique())->orderBy('position')->get()->groupBy('journal_id');
        foreach ($payments->groupBy('invoice_id') as $invoicePayments) {
            $first = $invoicePayments->first();
            $remaining = [];
            foreach ($components->get($first->invoice_journal, collect()) as $line) {
                if ($line->account_id === $first->control_account_id) continue;
                $remaining[$line->account_id] = ($remaining[$line->account_id] ?? 0) + $line->debit_minor + $line->credit_minor;
            }
            $events = [];
            foreach ($invoicePayments as $payment) {
                $events[] = ['date' => $payment->posting_date, 'order' => $payment->journal_id, 'payment' => $payment, 'reverse' => false];
                if ($payment->reversal_id && $payment->reversal_date <= $to) {
                    $events[] = ['date' => $payment->reversal_date, 'order' => $payment->reversal_id, 'payment' => $payment, 'reverse' => true];
                }
            }
            usort($events, fn ($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);
            $allocated = [];
            foreach ($events as $event) {
                $payment = $event['payment'];
                if ($event['reverse']) {
                    $parts = $allocated[$payment->id];
                    foreach ($parts as $account => $value) $remaining[$account] += $value;
                } else {
                    // Allocate across all invoice components, including tax/assets, to avoid counting them as profit.
                    $parts = self::allocate($payment->amount_minor, $remaining);
                    $allocated[$payment->id] = $parts;
                    foreach ($parts as $account => $value) $remaining[$account] -= $value;
                }
                if ($event['date'] >= $from) {
                    foreach ($parts as $account => $value) $add($account, $event['reverse'] ? -$value : $value);
                }
            }
        }

        // Direct cash/bank journals and cash vouchers. Invoice/payment sources are handled above exactly once.
        $manual = DB::table('accounting_journals as j')->where('j.company_id', $company)
            ->whereIn('j.status', ['posted', 'reversed'])->whereBetween('j.posting_date', [$from, $to])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('accounting_invoices as i')->whereRaw('i.journal_id = COALESCE(j.reversal_of, j.id)'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('accounting_payments as p')->whereRaw('p.journal_id = COALESCE(j.reversal_of, j.id)'))->get(['j.*']);
        $manualLines = DB::table('accounting_journal_lines')->where('company_id', $company)
            ->whereIn('journal_id', $manual->pluck('id'))->get()->groupBy('journal_id');
        $decisions = DB::table('accounting_cash_basis_reviews')->where('company_id', $company)
            ->whereIn('journal_id', $manual->map(fn ($j) => $j->reversal_of ?: $j->id)->unique())->get()->keyBy('journal_id');
        $decisionLines = DB::table('accounting_cash_basis_review_lines')->where('company_id', $company)->whereIn('review_id', $decisions->pluck('id'))->get()->groupBy('review_id');
        $review = [];
        $resolved = [];
        foreach ($manual as $journal) {
            $lines = $manualLines->get($journal->id, collect());
            $classification = self::classify($lines, $accounts);
            $netCash = $classification['net_cash'];
            $profitLines = $classification['profit_lines'];
            if ($classification['needs_review'] && ($decision = $decisions->get($journal->reversal_of ?: $journal->id))) {
                foreach ($decisionLines->get($decision->id, collect()) as $allocation) {
                    $add((int) $allocation->account_id, (int) $allocation->amount_minor * ($journal->reversal_of ? -1 : 1));
                }
                $resolved[] = ['id' => $journal->id, 'number' => $journal->number, 'posting_date' => $journal->posting_date,
                    'decision' => $decision->decision, 'reviewed_by' => $decision->reviewed_by, 'note' => $decision->note];
                continue;
            }
            if ($classification['skip']) continue;
            $sameSide = $classification['same_side'];
            if ($netCash !== 0 && $profitLines->isNotEmpty() && $sameSide) {
                foreach ($profitLines as $line) {
                    $income = $accounts[$line->account_id]->root_type === 'Income';
                    $add($line->account_id, $income ? $line->credit_minor - $line->debit_minor : $line->debit_minor - $line->credit_minor);
                }
            } elseif ($classification['needs_review']) {
                $review[] = ['id' => $journal->id, 'number' => $journal->number, 'posting_date' => $journal->posting_date,
                    'description' => $journal->remarks, 'net_cash' => Ledger::money($netCash), 'reason' => 'Cash movement needs an explicit income/expense allocation.'];
            }
        }
        $income = $expense = 0;
        $rows = [];
        foreach ($accounts->sortBy('code') as $account) {
            if ($account->is_group || ! in_array($account->root_type, ['Income', 'Expense'])) continue;
            $value = $amounts[$account->id] ?? 0;
            if ($account->root_type === 'Income') $income += $value;
            else $expense += $value;
            $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'root_type' => $account->root_type, 'amount' => Ledger::money($value)];
        }
        return ['report' => 'profit', 'basis' => 'cash', 'currency' => $currency, 'from' => $from, 'to' => $to,
            'rows' => $rows, 'summary' => ['income' => Ledger::money($income), 'expense' => Ledger::money($expense), 'profit' => Ledger::money($income - $expense)],
            'cash_basis_review' => $review, 'cash_basis_resolved' => $resolved, 'cash_basis_complete' => count($review) === 0];
    }

    public static function classify($lines, $accounts): array
    {
        $cash = $lines->filter(fn ($l) => in_array($accounts[$l->account_id]->account_type, ['Cash', 'Bank']) && $accounts[$l->account_id]->root_type === 'Asset');
        $net = (int) $cash->sum(fn ($l) => $l->debit_minor - $l->credit_minor);
        $other = $lines->reject(fn ($l) => $cash->contains('id', $l->id));
        $profit = $other->filter(fn ($l) => in_array($accounts[$l->account_id]->root_type, ['Income', 'Expense']));
        $skip = $cash->isEmpty() || ($net === 0 && $profit->isEmpty());
        $sameSide = $other->every(fn ($l) => $net > 0 ? $l->credit_minor > 0 : $l->debit_minor > 0);
        $automatic = $net !== 0 && $profit->isNotEmpty() && $sameSide;
        $needsReview = ! $skip && ! $automatic && ($profit->isNotEmpty() || $other->contains(fn ($l) => $accounts[$l->account_id]->account_type === 'General' && in_array($accounts[$l->account_id]->root_type, ['Asset', 'Liability'])));
        return ['net_cash' => $net, 'profit_lines' => $profit, 'skip' => $skip, 'same_side' => $sameSide, 'needs_review' => $needsReview];
    }

    // Integer-only proportional allocation; all pennies are assigned, and final settlement clears every component.
    private static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        abort_unless($amount > 0 && $amount <= $total, 422, 'Invoice payment allocation is inconsistent. Review the invoice payments.');
        $parts = $remainders = [];
        foreach ($weights as $account => $weight) {
            $quotient = $remainder = 0;
            // Compute amount * weight / total without overflowing a 64-bit integer.
            foreach (str_split(decbin($amount)) as $bit) {
                $remainder = $remainder * 2 + ($bit === '1' ? $weight : 0);
                $quotient = $quotient * 2 + intdiv($remainder, $total);
                $remainder %= $total;
            }
            $parts[$account] = $quotient;
            $remainders[$account] = $remainder;
        }
        arsort($remainders, SORT_NUMERIC);
        $left = $amount - array_sum($parts);
        foreach ($remainders as $account => $remainder) {
            if ($left <= 0) break;
            $parts[$account]++;
            $left--;
        }
        return $parts;
    }
}
