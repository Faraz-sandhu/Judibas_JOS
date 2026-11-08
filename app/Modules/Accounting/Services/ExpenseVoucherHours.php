<?php
namespace App\Modules\Accounting\Services;

use App\Services\PlatformAccess;
use Illuminate\Http\Request;

class ExpenseVoucherHours
{
    public static function enforce(Request $request, object $book, string $kind, array $lines = []): void
    {
        if (PlatformAccess::super($request)) return;
        $debits = array_column(array_filter($lines, fn ($line) => ($line['debit_minor'] ?? 0) > 0), 'account_id');
        $hasExpense = $debits && \Illuminate\Support\Facades\DB::table('accounting_accounts')
            ->whereIn('id', $debits)->where('root_type', 'Expense')->exists();
        if ($kind !== 'expense' && !$hasExpense) return;
        $open = $book->expense_open_time ?? '11:00';
        $close = $book->expense_close_time ?? '20:00';
        $time = now('Asia/Karachi')->format('H:i');
        $openLabel=\Carbon\Carbon::createFromFormat('H:i',$open)->format('g:i A');
        $closeLabel=\Carbon\Carbon::createFromFormat('H:i',$close)->format('g:i A');
        abort_unless($time >= $open && $time < $close, 422,
            "Expense vouchers can only be created or saved between {$openLabel} and {$closeLabel} (Pakistan time). Contact your Super Admin if needed.");
    }
}
