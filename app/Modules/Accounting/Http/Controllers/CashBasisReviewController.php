<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, CashBasisProfit, Ledger};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashBasisReviewController
{
    private function context(int $company, int $id): array
    {
        $selected = Ledger::document($company, $id);
        $source = $selected['reversal_of'] ? Ledger::document($company, $selected['reversal_of']) : $selected;
        abort_unless(in_array($source['status'], ['posted', 'reversed']), 422, 'Only posted manual entries can be reviewed.');
        Ledger::requireManual($company, $source['id']);
        $accounts = DB::table('accounting_accounts')->where('company_id', $company)->get()->keyBy('id');
        $lines = DB::table('accounting_journal_lines')->where('company_id', $company)->where('journal_id', $source['id'])->get();
        $classification = CashBasisProfit::classify($lines, $accounts);
        abort_unless($classification['needs_review'], 422, 'This entry does not require a manual cash-basis allocation.');
        $cash = $lines->filter(fn ($l) => $accounts[$l->account_id]->root_type === 'Asset' && in_array($accounts[$l->account_id]->account_type, ['Cash', 'Bank']));
        $profit = $classification['profit_lines'];
        $limits = ['in' => max((int) $cash->sum('debit_minor'), (int) $profit->sum('credit_minor')),
            'out' => max((int) $cash->sum('credit_minor'), (int) $profit->sum('debit_minor'))];
        return [$source, $selected, $classification['net_cash'], $limits];
    }

    public function show(Request $r, int $id)
    {
        $company = (int) $r->validate(['company_id' => 'required|integer'])['company_id'];
        Access::company($r, $company, 'cash_reviews.view');
        [$journal, $selected, $net] = $this->context($company, $id);
        $book = Ledger::book($company);
        $review = DB::table('accounting_cash_basis_reviews')->where('company_id', $company)->where('journal_id', $journal['id'])->first();
        $lines = $review ? DB::table('accounting_cash_basis_review_lines')->where('company_id', $company)->where('review_id', $review->id)->orderBy('id')->get() : collect();
        $locked = $book->locked_through && $journal['posting_date'] <= $book->locked_through;
        return ['journal' => $journal, 'selected_number' => $selected['number'], 'net_cash' => Ledger::money($net), 'currency' => $book->currency,
            'can_resolve' => ! $locked && \App\Modules\Accounting\Services\Permissions::can($r, 'cash_reviews.resolve'), 'period_locked' => (bool) $locked,
            'review' => ['version' => $review->version ?? 0, 'decision' => $review->decision ?? '', 'note' => $review->note ?? '',
                'outside_amount' => Ledger::money(abs($review->outside_minor ?? 0)), 'reviewed_by' => $review->reviewed_by ?? null,
                'allocations' => $lines->map(fn ($l) => ['account_id' => $l->account_id, 'amount' => Ledger::money(abs($l->amount_minor)), 'refund' => $l->amount_minor < 0])]];
    }

    public function save(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:0', 'decision' => 'required|in:allocate,outside',
            'note' => 'required|string|min:5|max:2000', 'outside_amount' => 'nullable',
            'allocations' => 'required_if:decision,allocate|array|max:100', 'allocations.*.account_id' => 'required|integer|distinct',
            'allocations.*.amount' => 'required', 'allocations.*.refund' => 'required|boolean']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'cash_reviews.resolve');
        DB::transaction(function () use ($r, $v, $company, $id) {
            Ledger::lock($company);
            [$journal, , $net, $limits] = $this->context($company, $id);
            Ledger::checkDate(Ledger::book($company), $journal['posting_date']);
            $old = DB::table('accounting_cash_basis_reviews')->where('company_id', $company)->where('journal_id', $journal['id'])->first();
            abort_unless((int) ($old->version ?? 0) === (int) $v['version'], 409, 'This allocation was changed by another person. Close and reopen the entry before saving.');
            $note = trim($v['note']);
            abort_if(mb_strlen($note) < 5, 422, 'Explain the classification in at least five characters.');
            $allocations = [];
            $outside = $net;
            if ($v['decision'] === 'allocate') {
                abort_if(empty($v['allocations']), 422, 'Add at least one income or expense allocation.');
                $cashRepresented = $cashIn = $cashOut = 0;
                foreach ($v['allocations'] as $line) {
                    $account = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $line['account_id'])->first();
                    abort_unless($account && $account->active && ! $account->is_group && in_array($account->root_type, ['Income', 'Expense']), 422, 'Select an active income or expense ledger account in this company.');
                    $amount = Ledger::minor($line['amount']);
                    abort_if($amount <= 0, 422, 'Each allocation amount must be greater than zero.');
                    if ($line['refund']) $amount = -$amount;
                    $cashEffect = $account->root_type === 'Income' ? $amount : -$amount;
                    $cashRepresented += $cashEffect;
                    $cashIn += max(0, $cashEffect);
                    $cashOut += max(0, -$cashEffect);
                    $allocations[] = ['company_id' => $company, 'account_id' => $account->id, 'amount_minor' => $amount];
                }
                $outsideAmount = Ledger::minor($v['outside_amount'] ?? '0.00');
                abort_if($outsideAmount > abs($net), 422, 'The amount outside Profit and Loss exceeds the net cash movement.');
                $outside = $outsideAmount * ($net < 0 ? -1 : ($net > 0 ? 1 : 0));
                abort_if($outsideAmount > 0 && $net === 0, 422, 'A zero net cash movement cannot contain an unallocated outside amount.');
                abort_if($cashIn + max(0, $outside) > $limits['in'] || $cashOut + max(0, -$outside) > $limits['out'], 422, 'The allocated receipts or payments exceed the amounts supported by the original journal.');
                abort_unless($cashRepresented + $outside === $net, 422, 'The allocations and amount outside Profit and Loss must match the journal cash movement exactly.');
            } else {
                abort_if(! empty($v['allocations']), 422, 'An outside Profit and Loss classification cannot contain income/expense allocations.');
            }
            $previous = $old ? ['review' => (array) $old, 'allocations' => DB::table('accounting_cash_basis_review_lines')->where('review_id', $old->id)->get()->all()] : null;
            $values = ['decision' => $v['decision'], 'note' => $note, 'outside_minor' => $outside, 'version' => ($old->version ?? 0) + 1,
                'reviewed_by' => PlatformAccess::super($r) ? 'Super Admin' : $r->user()->name, 'updated_at' => now()];
            if ($old) {
                $reviewId = $old->id;
                DB::table('accounting_cash_basis_reviews')->where('id', $reviewId)->update($values);
                DB::table('accounting_cash_basis_review_lines')->where('review_id', $reviewId)->delete();
            } else {
                $reviewId = DB::table('accounting_cash_basis_reviews')->insertGetId($values + ['company_id' => $company, 'journal_id' => $journal['id'], 'created_at' => now()]);
            }
            if ($allocations) DB::table('accounting_cash_basis_review_lines')->insert(array_map(fn ($line) => $line + ['review_id' => $reviewId], $allocations));
            Access::audit($r, $company, 'Cash-basis allocation saved', $journal['number'], ['previous' => $previous, 'review' => $values, 'allocations' => $allocations]);
        });
        return ['message' => 'Cash-basis review saved. Profit and Loss has been updated.'];
    }
}
