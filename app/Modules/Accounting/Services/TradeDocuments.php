<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradeDocuments
{
    public static function account(int $company, int $id, array $types = [], array $roots = []): object
    {
        $a = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $id)->first();
        abort_unless($a && $a->active && ! $a->is_group && (! $types || in_array($a->account_type, $types)) && (! $roots || in_array($a->root_type, $roots)), 422, 'Choose an active account with the correct type in this company.');

        return $a;
    }

    public static function invoice(int $company, int $id): array
    {
        $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
        abort_unless($i, 404);
        $paid = (int) DB::table('accounting_payments')->where('invoice_id', $id)->where('status', 'posted')->sum('amount_minor');
        $payments = DB::table('accounting_payments')->where('invoice_id', $id)->orderByDesc('id')->get()->map(fn ($p) => array_merge((array) $p, ['amount' => Ledger::money($p->amount_minor)]));

        return array_merge((array) $i, ['lines' => json_decode($i->lines, true), 'net' => Ledger::money($i->net_minor), 'tax' => Ledger::money($i->tax_minor), 'total' => Ledger::money($i->total_minor), 'tax_percent' => Ledger::money($i->tax_basis_points), 'paid' => Ledger::money($paid), 'outstanding' => Ledger::money($i->status === 'reversed' ? 0 : $i->total_minor - $paid), 'payments' => $payments]);
    }

    public static function journal(Request $r, int $company, string $date, string $remarks, array $lines): int
    {
        Ledger::checkDate(Ledger::book($company), $date);
        $number = DB::table('accounting_journals')->where('company_id', $company)->count() + 1;
        $id = DB::table('accounting_journals')->insertGetId(['company_id' => $company, 'request_key' => (string) Str::uuid(), 'number' => 'JV-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT), 'posting_date' => $date, 'remarks' => $remarks, 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($lines as $position => $line) {
            DB::table('accounting_journal_lines')->insert($line + ['company_id' => $company, 'journal_id' => $id, 'position' => $position + 1]);
        }
        DB::table('accounting_journals')->where('id', $id)->update(['status' => 'posted', 'posted_by' => $r->user()?->id, 'posted_at' => now(), 'version' => 2, 'updated_at' => now()]);

        return $id;
    }
}
