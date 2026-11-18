<?php

namespace App\Modules\Accounting\Services;

use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImprestFunds
{
    public static function restricted(Request $r): bool
    {
        if (PlatformAccess::administrator($r) || ! $r->user()) {
            return false;
        }
        $q = DB::table('accounting_imprest_funds')->where('user_id', $r->user()->id);
        if (! $r->is('accounting/api/roles') && is_scalar($r->input('company_id')) && ctype_digit((string) $r->input('company_id'))) {
            $q->where('company_id', (int) $r->input('company_id'));
        }

        return $q->exists();
    }

    public static function tagAccounts($accounts)
    {
        $ids = DB::table('accounting_imprest_funds')->whereIn('account_id', collect($accounts)->pluck('id'))->pluck('account_id')->all();

        return collect($accounts)->map(function ($account) use ($ids) {
            $account->imprest_fund = in_array($account->id, $ids, true);

            return $account;
        });
    }

    public static function protectedAccount(int $id): bool
    {
        return DB::table('accounting_imprest_funds')->where('account_id', $id)->exists();
    }

    public static function balance(object $fund, ?string $through = null): int
    {
        return (int) DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('l.company_id', $fund->company_id)->where('l.account_id', $fund->account_id)
            ->whereIn('j.status', ['posted', 'reversed'])->when($through, fn ($q) => $q->where('j.posting_date', '<=', $through))
            ->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as balance')->value('balance');
    }

    public static function reserved(object $fund, ?int $ignore = null): int
    {
        return (int) DB::table('accounting_imprest_movements as m')->join('accounting_journals as j', 'j.id', '=', 'm.journal_id')
            ->join('accounting_journal_lines as l', 'l.journal_id', '=', 'j.id')->where('m.fund_id', $fund->id)
            ->where('m.type', 'spending')->where('m.void', false)->where('j.status', 'draft')->where('l.account_id', $fund->account_id)
            ->when($ignore, fn ($q) => $q->where('j.id', '!=', $ignore))->sum('l.credit_minor');
    }

    public static function available(object $fund, string $date, ?int $ignore = null): int
    {
        // A backdated payment must not make any subsequent day's fund balance negative.
        $minimum = $running = self::balance($fund, $date);
        $days = DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('l.account_id', $fund->account_id)->whereIn('j.status', ['posted', 'reversed'])
            ->where('j.posting_date', '>', $date)->groupBy('j.posting_date')->orderBy('j.posting_date')
            ->selectRaw('j.posting_date, SUM(l.debit_minor-l.credit_minor) as change')->get();
        foreach ($days as $day) {
            $running += (int) $day->change;
            $minimum = min($minimum, $running);
        }

        return max(0, $minimum - self::reserved($fund, $ignore));
    }

    public static function requireAvailable(object $fund, string $date, int $amount, ?int $ignore = null): void
    {
        $available = self::available($fund, $date, $ignore);
        abort_if($amount > $available, 422, 'Insufficient assigned imprest funds. Available after pending vouchers: '.Ledger::book($fund->company_id)->currency.' '.Ledger::money($available).'.');
    }

    public static function movement(int $id): ?object
    {
        return DB::table('accounting_imprest_movements')->where('journal_id', $id)->first();
    }

    public static function authorizeJournal(Request $r, int $company, int $id): void
    {
        if (! self::restricted($r)) {
            return;
        }
        abort_unless(DB::table('accounting_imprest_movements as m')->join('accounting_imprest_funds as f', 'f.id', '=', 'm.fund_id')
            ->where('m.journal_id', $id)->where('f.company_id', $company)->where('f.user_id', $r->user()?->id)->exists(), 403, 'You can only access vouchers belonging to your assigned fund.');
    }

    public static function guardPost(int $company, object $journal): void
    {
        $movement = self::movement($journal->id);
        $lines = DB::table('accounting_journal_lines')->where('journal_id', $journal->id)->get();
        if (! $movement) {
            abort_if(DB::table('accounting_imprest_funds')->whereIn('account_id', $lines->pluck('account_id'))->exists(), 422, 'Use Imprest / Petty Cash to transact with an officer fund.');

            return;
        }
        abort_if($movement->void, 422, 'This imprest draft was cancelled and cannot be posted.');
        $fund = DB::table('accounting_imprest_funds')->where('company_id', $company)->find($movement->fund_id);
        abort_unless($fund && $fund->active, 422, 'This imprest fund is paused.');
        if ($movement->type === 'spending') {
            self::requireAvailable($fund, $journal->posting_date, (int) $lines->where('account_id', $fund->account_id)->sum('credit_minor'), $journal->id);
        }
        // Includes the company's cash/bank source for advance vouchers (general journals).
        Ledger::requireCashFunds($company, 'cash_payment', $journal->posting_date, $lines->map(fn ($l) => (array) $l)->all());
    }

    public static function guardReverse(int $company, int $id, string $date): void
    {
        foreach (DB::table('accounting_journal_lines')->where('journal_id', $id)->where('debit_minor', '>', 0)->get() as $line) {
            $fund = DB::table('accounting_imprest_funds')->where('company_id', $company)->where('account_id', $line->account_id)->first();
            if ($fund) {
                self::requireAvailable($fund, $date, (int) $line->debit_minor);
            }
        }
    }
}
