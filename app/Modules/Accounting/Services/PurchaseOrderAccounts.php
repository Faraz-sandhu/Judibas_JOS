<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class PurchaseOrderAccounts
{
    public static function ids(int $company): array
    {
        $accounts = DB::table('accounting_accounts')->where('company_id', $company)->get()->keyBy('id');

        return $accounts->filter(function ($account) use ($accounts) {
            if (! $account->active || $account->is_group || $account->root_type !== 'Expense') {
                return false;
            }
            $node = $account;
            $seen = [];
            while ($node && ! isset($seen[$node->id])) {
                $seen[$node->id] = true;
                if ($node->account_type === 'Cost of Sales' || in_array(strtolower(trim($node->name)), ['cost of sales', 'cost of sale', 'cost of goods sold', 'cost of services'])) {
                    return true;
                }
                $node = $accounts->get($node->parent_id);
            }

            return false;
        })->keys()->all();
    }
}
