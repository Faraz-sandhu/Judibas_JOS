<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ledger
{
    public const MAX_MINOR = 999999999999;

    public static function minor(mixed $amount): int
    {
        abort_unless(is_string($amount) || is_int($amount) || is_float($amount), 422, 'Enter a valid monetary amount.');
        $text = (string) $amount;
        abort_unless(preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text), 422, 'Amounts must be positive numbers with at most two decimal places.');
        [$whole, $fraction] = array_pad(explode('.', $text), 2, '');
        $minor = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        abort_if($minor > self::MAX_MINOR, 422, 'The amount exceeds the supported limit.');

        return $minor;
    }

    public static function money(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function lock(int $company): void
    {
        DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first();
    }

    public static function book(int $company): object
    {
        $book = DB::table('accounting_books')->where('company_id', $company)->first();
        abort_unless($book, 422, 'Set up the company books before entering transactions.');

        return $book;
    }

    public static function checkDate(object $book, string $date): void
    {
        abort_if($date < $book->start_date, 422, 'The posting date is before the start of these books.');
        abort_if($book->locked_through && $date <= $book->locked_through, 422, 'This accounting period is locked. Choose an open posting date.');
        abort_if($date > now()->toDateString(), 422, 'Future-dated entries cannot be posted.');
    }

    public static function lines(int $company, array $input): array
    {
        $lines = [];
        $debit = $credit = 0;
        foreach ($input as $index => $line) {
            $account = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $line['account_id'])->first();
            abort_unless($account && $account->active && ! $account->is_group, 422, 'Choose an active ledger account in this company for every line.');
            // Party ledgers require invoice/payment allocation, which general journals must not bypass.
            abort_if(in_array($account->account_type, ['Receivable', 'Payable']), 422, 'Customer and supplier control accounts require a party transaction. Use a general ledger account for this journal.');
            $d = self::minor($line['debit']);
            $c = self::minor($line['credit']);
            abort_unless(($d > 0 && $c === 0) || ($c > 0 && $d === 0), 422, 'Each line must contain either a debit or a credit, never both.');
            $debit += $d;
            $credit += $c;
            $lines[] = ['company_id' => $company, 'account_id' => $account->id, 'position' => $index + 1, 'debit_minor' => $d, 'credit_minor' => $c, 'description' => $line['description'] ?? null];
        }
        abort_unless(count($lines) >= 2 && $debit === $credit && $debit > 0, 422, 'Total debits must equal total credits and be greater than zero.');

        return $lines;
    }

    public static function document(int $company, int $id): array
    {
        $row = DB::table('accounting_journals')->where('company_id', $company)->where('id', $id)->first();
        abort_unless($row, 404);
        $lines = DB::table('accounting_journal_lines as l')->join('accounting_accounts as a', 'a.id', '=', 'l.account_id')->where('l.journal_id', $id)->orderBy('l.position')->get(['l.*', 'a.code as account_code', 'a.name as account_name']);
        $row->lines = $lines->map(fn ($l) => array_merge((array) $l, ['debit' => self::money($l->debit_minor), 'credit' => self::money($l->credit_minor)]));
        $row->total = self::money((int) $lines->sum('debit_minor'));
        $row->reversal_id = DB::table('accounting_journals')->where('reversal_of', $id)->value('id');

        return (array) $row;
    }

    public static function post(Request $r, int $company, object $journal): void
    {
        abort_unless($journal->status === 'draft', 409, 'Only a draft can be posted.');
        self::checkDate(self::book($company), $journal->posting_date);
        $lines = DB::table('accounting_journal_lines')->where('journal_id', $journal->id)->get()->map(fn ($l) => ['account_id' => $l->account_id, 'debit' => self::money($l->debit_minor), 'credit' => self::money($l->credit_minor), 'description' => $l->description])->all();
        self::lines($company, $lines);
        DB::table('accounting_journals')->where('id', $journal->id)->update(['status' => 'posted', 'posted_by' => $r->user()?->id, 'posted_at' => now(), 'version' => $journal->version + 1, 'updated_at' => now()]);
        AccountingAccess::audit($r, $company, 'Journal posted', $journal->number);
    }
    public static function requireManual(int $company, int $id): void
    {
        abort_if(DB::table('accounting_invoices')->where('company_id', $company)->where('journal_id', $id)->exists() || DB::table('accounting_payments')->where('company_id', $company)->where('journal_id', $id)->exists(), 422, 'Manage this journal through its original invoice or payment.');
    }

    public static function reverse(Request $r, int $company, int $id, array $v): int
    {
            $original = DB::table('accounting_journals')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($original, 404);
            abort_unless($original->status === 'posted' && ! $original->reversal_of, 409, 'Only an original posted entry can be reversed once.');
            abort_unless($original->version === (int) $v['version'], 409, 'This journal changed. Reopen it before reversing.');
            self::checkDate(self::book($company), $v['posting_date']);
            abort_if($v['posting_date'] < $original->posting_date, 422, 'A reversal cannot be dated before the original entry.');
            $number = DB::table('accounting_journals')->where('company_id', $company)->count() + 1;
            $rid = DB::table('accounting_journals')->insertGetId(['company_id' => $company, 'request_key' => (string) \Illuminate\Support\Str::uuid(), 'number' => 'JV-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT), 'posting_date' => $v['posting_date'], 'kind' => 'reversal', 'remarks' => 'Reversal of '.$original->number.': '.trim($v['reason']), 'reversal_of' => $id, 'reversal_reason' => trim($v['reason']), 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            $lines = DB::table('accounting_journal_lines')->where('journal_id', $id)->get();
            foreach ($lines as $l) {
                DB::table('accounting_journal_lines')->insert(['company_id' => $company, 'journal_id' => $rid, 'account_id' => $l->account_id, 'position' => $l->position, 'debit_minor' => $l->credit_minor, 'credit_minor' => $l->debit_minor, 'description' => $l->description, 'party_id' => $l->party_id]);
            }
            // A reversal must remain possible after a ledger account has been deactivated.
            DB::table('accounting_journals')->where('id', $rid)->update(['status' => 'posted', 'posted_by' => $r->user()?->id, 'posted_at' => now(), 'version' => 2, 'updated_at' => now()]);
            DB::table('accounting_journals')->where('id', $id)->update(['status' => 'reversed', 'version' => $original->version + 1, 'updated_at' => now()]);
            AccountingAccess::audit($r, $company, 'Journal reversed', $original->number, ['reason' => trim($v['reason']), 'reversal_id' => $rid]);

            return $rid;
    }
}
