<?php
namespace App\Modules\Accounting\Services;

class VoucherTypes
{
    public const TYPES = [
        'contra' => ['CV', 'Contra Voucher', 'journals', null, false],
        'cash_payment' => ['CPV', 'Cash Payment Voucher', 'cash_payments', 'Cash', false],
        'cash_receipt' => ['CRV', 'Cash Receipt Voucher', 'cash_receipts', 'Cash', true],
        'bank_payment' => ['BPV', 'Bank Payment Voucher', 'bank_payments', 'Bank', false],
        'bank_receipt' => ['BRV', 'Bank Receipt Voucher', 'bank_receipts', 'Bank', true],
        'expense' => ['EV', 'Expense Voucher', 'expense_vouchers', null, false],
        'income' => ['IV', 'Income Voucher', 'income_vouchers', null, true],
    ];
}
