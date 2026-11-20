<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\TradeDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController
{
    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'usage' => 'nullable|in:sale,purchase', 'search' => 'nullable|string|max:150', 'status' => 'nullable|in:active,inactive', 'page' => 'nullable|integer|min:1']);
        Access::company($r, (int) $v['company_id'], 'items.view');
        $q = DB::table('accounting_items')->where('company_id', $v['company_id']);
        if (! empty($v['usage'])) {
            $q->where('active', true)->where($v['usage'] === 'sale' ? 'sellable' : 'purchasable', true);
        }$q->when($v['status'] ?? null, fn ($q, $s) => $q->where('active', $s === 'active'))->when($v['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'ilike', '%'.$s.'%')->orWhere('code', 'ilike', '%'.$s.'%')));
        $rows = $q->orderBy('name')->paginate(20);
        $rows->getCollection()->transform(fn ($i) => array_merge((array) $i, ['sale_rate' => Ledger::money($i->sale_rate_minor), 'purchase_rate' => Ledger::money($i->purchase_rate_minor)]));

        return $rows;
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => 'required_with:id|integer|min:1', 'code' => 'required|string|max:50', 'name' => 'required|string|max:150', 'kind' => 'required|in:service,product', 'unit' => 'required|string|max:30', 'description' => 'nullable|string|max:250', 'sale_rate' => ['required','regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'purchase_rate' => ['required','regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'sellable' => 'required|boolean', 'purchasable' => 'required|boolean', 'income_account_id' => 'nullable|integer', 'expense_account_id' => 'nullable|integer', 'active' => 'required|boolean']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, empty($v['id']) ? 'items.create' : 'items.update');

        return DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $old = empty($v['id']) ? null : DB::table('accounting_items')->where('company_id', $company)->where('id', $v['id'])->first();
            abort_if(! empty($v['id']) && ! $old, 404);
            abort_if($old && $old->version !== (int) $v['version'], 409, 'Item changed. Reopen before saving.');
            $code = strtoupper(trim($v['code']));
            abort_if($code === '' || trim($v['name']) === '' || trim($v['unit']) === '', 422, 'Code, name and unit cannot be empty.');
            abort_if(DB::table('accounting_items')->where('company_id', $company)->where('code', $code)->when($old, fn ($q) => $q->where('id', '<>', $old->id))->exists(), 422, 'This item code already exists in this company.');
            foreach (['income_account_id' => ['Income'], 'expense_account_id' => ['Expense', 'Asset']] as $field => $roots) {
                if (! empty($v[$field])) {
                    $a = TradeDocuments::account($company, $v[$field], [], $roots);
                    abort_if(in_array($a->account_type, ['Cash', 'Bank', 'Receivable', 'Payable']), 422, 'Choose an income, expense or non-cash asset ledger for this item.');
                }
            }
            $values = ['code' => $code, 'name' => trim($v['name']), 'kind' => $v['kind'], 'unit' => trim($v['unit']), 'description' => $v['description'] ?? null, 'sale_rate_minor' => Ledger::minor($v['sale_rate']), 'purchase_rate_minor' => Ledger::minor($v['purchase_rate']), 'sellable' => $v['sellable'], 'purchasable' => $v['purchasable'], 'income_account_id' => $v['income_account_id'] ?? null, 'expense_account_id' => $v['expense_account_id'] ?? null, 'active' => $v['active'], 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                DB::table('accounting_items')->where('id', $old->id)->update($values);
            } else {
                DB::table('accounting_items')->insert($values + ['company_id' => $company, 'created_at' => now()]);
            }Access::audit($r,$company,$old ? 'Item updated' : 'Item created',$code);

            return ['message' => 'Item / service saved.'];
        });
    }
}
