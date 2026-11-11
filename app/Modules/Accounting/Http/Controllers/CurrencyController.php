<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\Currency;
use App\Modules\Accounting\Services\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyController
{
    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer']);
        $c = (int) $v['company_id'];
        Access::company($r, $c);

        return ['base' => Ledger::book($c)->currency, 'currencies' => Currency::CODES, 'rates' => DB::table('accounting_exchange_rates')->where('company_id', $c)->orderByDesc('rate_date')->limit(200)->get()];
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'currency' => 'required|string|size:3', 'rate_date' => 'required|date_format:Y-m-d', 'exchange_rate' => 'required']);
        $c = (int) $v['company_id'];
        Access::company($r, $c, 'books.manage');

        return DB::transaction(function () use ($r, $v, $c) {
            Ledger::lock($c);
            $context = Currency::context($c, $v);
            abort_if($context['currency'] === Ledger::book($c)->currency, 422, 'The base currency rate is fixed at 1.');
            DB::table('accounting_exchange_rates')->updateOrInsert(['company_id' => $c, 'currency' => $context['currency'], 'rate_date' => $v['rate_date']], ['rate' => $context['exchange_rate'], 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $c, 'Exchange rate saved', $context['currency'], ['date' => $v['rate_date'], 'rate' => $context['exchange_rate']]);

            return ['message' => 'Exchange rate saved. Existing transactions retain their saved rates.'];
        });
    }
}
