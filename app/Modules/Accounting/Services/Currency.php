<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class Currency
{
    public const CODES = ['PKR', 'USD', 'AED', 'GBP', 'EUR', 'SAR', 'CAD', 'AUD'];

    public static function context(int $company, array $v): array
    {
        $base = Ledger::book($company)->currency;
        $code = $v['currency'] ?? $base;
        abort_unless(in_array($code, self::CODES, true), 422, 'Select a supported currency.');
        $text = (string) ($v['exchange_rate'] ?? '1');
        abort_unless(preg_match('/^\d{1,6}(?:\.\d{1,6})?$/D', $text), 422, 'Exchange rate must be positive with up to six decimal places.');
        [$whole,$fraction] = array_pad(explode('.', $text), 2, '');
        $units = (int) $whole * 1000000 + (int) str_pad($fraction, 6, '0');
        abort_unless($units > 0, 422, 'Exchange rate must be greater than zero.');
        abort_if($code === $base && $units !== 1000000, 422, 'Base currency exchange rate must be 1.');
        abort_if($code !== $base && ! isset($v['exchange_rate']), 422, 'Enter the dated exchange rate for this transaction.');

        return ['currency' => $code, 'exchange_rate' => sprintf('%d.%06d', intdiv($units, 1000000), $units % 1000000)];
    }

    public static function convert(int $minor, string $rate): int
    {
        $u = (int) str_replace('.', '', $rate); // canonical rate, always six fractional digits
        $whole = intdiv($minor, 1000000);
        $part = $minor % 1000000;
        abort_if($whole > intdiv(Ledger::MAX_MINOR, $u), 422, 'Converted amount exceeds the supported limit.');
        $result = $whole * $u + intdiv($part * $u + 500000, 1000000);
        abort_if($result > Ledger::MAX_MINOR, 422, 'Converted amount exceeds the supported limit.');

        return $result;
    }

    public static function account(object $a, string $currency, int $company): void
    {
        $base = Ledger::book($company)->currency;
        abort_if($a->currency && $a->currency !== $currency, 422, 'Account '.$a->name.' requires '.$a->currency.'.');
        abort_if(! $a->currency && in_array($a->account_type, ['Bank', 'Cash']) && $currency !== $base, 422, 'Select a bank/cash account assigned to '.$currency.' or settle into a base-currency bank account.');
    }

    public static function tags(array $context, int $debit, int $credit): array
    {
        return $context + ['foreign_debit_minor' => $debit, 'foreign_credit_minor' => $credit];
    }

    public static function bankCost(int $company, int $account, int $units, string $date): int
    {
        $b = DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')->where('l.company_id', $company)->where('j.company_id', $company)->where('l.account_id', $account)->whereIn('j.status', ['posted', 'reversed'])->where('j.posting_date', '<=', $date)->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as base,COALESCE(SUM(COALESCE(l.foreign_debit_minor,0)-COALESCE(l.foreign_credit_minor,0)),0) as units')->first();
        abort_unless($b->units > 0 && $units <= $b->units && $b->base > 0, 422, 'Insufficient foreign bank/cash balance on the transaction date.');
        if ($units === (int) $b->units) {
            return (int) $b->base;
        }
        // Decimal division keeps proportional carrying-value allocations exact without float multiplication.
        $value = DB::selectOne('SELECT round(CAST(? AS numeric)*CAST(? AS numeric)/CAST(? AS numeric)) as amount', [(string) $b->base, (string) $units, (string) $b->units]);

        return (int) $value->amount;
    }

    public static function checkOutflows(int $company, array $lines, string $date): void
    {
        $groups = [];
        foreach ($lines as $line) {
            $a = DB::table('accounting_accounts')->find($line['account_id']);
            if (! $a->currency || ($line['foreign_credit_minor'] ?? 0) <= 0) {
                continue;
            }$groups[$a->id]['units'] = ($groups[$a->id]['units'] ?? 0) + $line['foreign_credit_minor'];
            $groups[$a->id]['base'] = ($groups[$a->id]['base'] ?? 0) + $line['credit_minor'];
        }
        foreach ($groups as $id => $amount) {
            $cost = self::bankCost($company, $id, $amount['units'], $date);
            abort_unless($cost === $amount['base'], 422, 'For manual foreign bank/cash outflows, use the account carrying exchange rate so original and base balances close correctly. Invoice payments allocate carrying value automatically.');
        }
    }

    public static function journalLines(int $company, array $input, array $context, bool $allowUnlinked = false): array
    {
        $lines = Ledger::lines($company, $input, $allowUnlinked);
        $debit = $credit = 0;
        foreach ($lines as &$line) {
            $a = DB::table('accounting_accounts')->find($line['account_id']);
            self::account($a, $context['currency'], $company);
            $d = $line['debit_minor'];
            $c = $line['credit_minor'];
            $line += self::tags($context, $d, $c);
            $line['debit_minor'] = self::convert($d,$context['exchange_rate']);
            $line['credit_minor'] = self::convert($c,$context['exchange_rate']);
            $debit += $line['debit_minor'];
            $credit += $line['credit_minor'];
        }
        abort_unless($debit === $credit && $debit > 0,422,'Converted journal must balance in the base currency. Adjust the lines for rounding.');

        return $lines;
    }
}
