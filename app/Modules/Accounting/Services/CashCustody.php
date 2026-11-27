<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class CashCustody
{
    public static function transfer(int $journal): ?object
    {
        return DB::table('accounting_cash_custody_transfers')->where('journal_id', $journal)->first();
    }

    public static function balance(int $account, ?string $date = null): int
    {
        return (int) DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('l.account_id', $account)->whereIn('j.status', ['posted', 'reversed'])
            ->when($date, fn ($q) => $q->where('j.posting_date', '<=', $date))
            ->selectRaw('COALESCE(SUM(l.debit_minor - l.credit_minor), 0) as balance')->value('balance');
    }

    public static function guardPost(object $journal): void
    {
        $transfer = self::transfer($journal->id);
        if (! $transfer) return;
        abort_unless($transfer->received_at, 422, 'The designated receiver must confirm physical cash receipt before this custody voucher can be posted.');
        $lines = DB::table('accounting_journal_lines')->where('journal_id', $journal->id)->orderBy('position')->get();
        abort_unless($journal->kind === 'contra' && $lines->count() === 2
            && (int) $lines[0]->account_id === (int) $transfer->destination_account_id
            && (int) $lines[1]->account_id === (int) $transfer->source_account_id
            && (int) $lines[0]->debit_minor === (int) $transfer->amount_minor && (int) $lines[0]->credit_minor === 0
            && (int) $lines[1]->credit_minor === (int) $transfer->amount_minor && (int) $lines[1]->debit_minor === 0,
            422, 'The custody voucher does not match its confirmed handover.');
        foreach ([$transfer->source_account_id, $transfer->destination_account_id] as $account) {
            abort_unless(DB::table('accounting_accounts')->where('id', $account)->where('company_id', $journal->company_id)->where('active', true)->where('is_group', false)->where('account_type', 'Cash')->exists(), 422, 'Cash custody requires two active Cash ledger accounts in this company.');
        }
    }
}
