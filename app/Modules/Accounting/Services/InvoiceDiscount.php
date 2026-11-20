<?php

namespace App\Modules\Accounting\Services;

class InvoiceDiscount
{
    // Integer long multiplication/division; avoids overflowing amount * subtotal.
    private static function share(int $amount, int $discount, int $subtotal): array
    {
        $q = 0;
        $r = 0;
        foreach (str_split((string) $amount) as $digit) {
            $n = $r * 10 + (int) $digit * $discount;
            $q = $q * 10 + intdiv($n, $subtotal);
            $r = $n % $subtotal;
        }

        return [$q, $r];
    }

    public static function apply(array &$lines, int $subtotal, string $type, mixed $value): int
    {
        $v = Ledger::minor($value ?? '0.00');
        if ($type === 'percent') {
            abort_if($v > 10000, 422, 'Discount percentage cannot exceed 100.');
            $discount = intdiv($subtotal * $v + 5000, 10000);
        } else {
            $discount = $v;
        }
        abort_unless($discount < $subtotal, 422, 'Discount must be less than the subtotal; invoice total must remain positive.');
        if (! $discount) {
            return 0;
        }
        $shares = [];
        $allocated = 0;
        foreach ($lines as $i => $line) {
            [$share,$remainder] = self::share($line['amount_minor'], $discount, $subtotal);
            $shares[$i] = ['amount' => $share, 'remainder' => $remainder];
            $allocated += $share;
        }
        $order = array_keys($shares);
        usort($order, fn ($a, $b) => ($shares[$b]['remainder'] <=> $shares[$a]['remainder']) ?: ($a <=> $b));
        for ($i = 0; $i < $discount - $allocated; $i++) {
            $shares[$order[$i]]['amount']++;
        }
        foreach ($lines as $i => &$line) {
            $line['subtotal_minor'] = $line['amount_minor'];
            $line['discount_minor'] = $shares[$i]['amount'];
            $line['amount_minor'] -= $shares[$i]['amount'];
            $line['amount'] = Ledger::money($line['amount_minor']);
        }unset($line);

        return $discount;
    }
}
