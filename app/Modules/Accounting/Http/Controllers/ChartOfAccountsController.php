<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChartOfAccountsController
{
    public const TYPES = ['Asset' => ['General', 'Bank', 'Cash', 'Receivable', 'Fixed Asset', 'Inventory'], 'Liability' => ['General', 'Payable', 'Tax'], 'Equity' => ['General', 'Capital', 'Retained Earnings'], 'Income' => ['General', 'Sales', 'Other Income'], 'Expense' => ['General', 'Cost of Sales', 'Operating Expense']];

    public function data(Request $r)
    {
        $role = Access::role($r);
        $companies = Access::companies($r);
        $r->validate(['company_id' => 'nullable|integer|min:1']);
        $company = (int) ($r->input('company_id') ?: ($companies->first()->id ?? 0));
        if ($company) {
            Access::company($r, $company);
        }

        return ['companies' => $companies, 'company_id' => $company ?: null, 'role' => $role, 'book' => $company ? DB::table('accounting_books')->where('company_id', $company)->first() : null, 'can_manage' => in_array($role, ['manager', 'accountant']), 'can_delete' => $role === 'manager', 'account_types' => self::TYPES, 'accounts' => $company ? DB::table('accounting_accounts')->where('company_id', $company)->orderBy('code')->get() : []];
    }

    private function lock(int $company): void
    {
        DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first();
    }

    public function initialize(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        DB::transaction(function () use ($r, $company) {
            $this->lock($company);
            abort_if(DB::table('accounting_accounts')->where('company_id', $company)->exists(), 422, 'This company already has a chart of accounts.');
            foreach (['Asset' => ['1000', 'Assets', [['1100', 'Current Assets', [['1110', 'Cash', 'Cash'], ['1120', 'Bank', 'Bank'], ['1130', 'Accounts Receivable', 'Receivable']]]]], 'Liability' => ['2000', 'Liabilities', [['2100', 'Current Liabilities', [['2110', 'Accounts Payable', 'Payable']]]]], 'Equity' => ['3000', 'Equity', [['3100', 'Owner Capital', 'Capital']]], 'Income' => ['4000', 'Income', [['4100', 'Service Revenue', 'Sales']]], 'Expense' => ['5000', 'Expenses', [['5100', 'Operating Expenses', [['5110', 'Office Expenses', 'Operating Expense']]]]]] as $root => $spec) {
                $id = $this->insert($company, null, $spec[0], $spec[1], $root, 'General', true, true);
                foreach ($spec[2] as $child) {
                    $group = is_array($child[2]);
                    $cid = $this->insert($company, $id, $child[0], $child[1], $root, $group ? 'General' : $child[2], $group);
                    if ($group) {
                        foreach ($child[2] as $leaf) {
                            $this->insert($company, $cid, $leaf[0], $leaf[1], $root, $leaf[2], false);
                        }
                    }
                }
            }
            Access::audit($r, $company, 'Chart initialized', 'Starter chart of accounts');
        });

        return ['message' => 'Starter chart created. Review it with your accountant before using it.'];
    }

    private function insert(int $company, ?int $parent, string $code, string $name, string $root, string $type, bool $group, bool $system = false): int
    {
        return DB::table('accounting_accounts')->insertGetId(['company_id' => $company, 'parent_id' => $parent, 'code' => $code, 'name' => $name, 'root_type' => $root, 'account_type' => $type, 'is_group' => $group, 'is_system' => $system, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function save(Request $r)
    {
        $v = $r->validate(['version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'id' => 'nullable|integer|min:1', 'company_id' => 'required|integer|min:1', 'parent_id' => 'required|integer|min:1', 'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/'], 'name' => 'required|string|max:150', 'root_type' => ['required', Rule::in(array_keys(self::TYPES))], 'account_type' => 'required|string', 'is_group' => 'required|boolean', 'active' => 'required|boolean', 'description' => 'nullable|string|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        $v['code'] = strtoupper(trim($v['code']));
        $v['name'] = trim($v['name']);
        abort_if($v['name'] === '', 422, 'Enter an account name.');
        $account = DB::transaction(function () use ($r, $v, $company) {
            $this->lock($company);
            $q = DB::table('accounting_accounts')->where('company_id', $company);
            $old = isset($v['id']) ? (clone $q)->where('id', $v['id'])->first() : null;
            abort_if(isset($v['id']) && ! $old, 404);
            abort_if($old && $old->version !== (int) ($v['version'] ?? 0), 409, 'This account was changed by another person. Close this form and reopen the account before saving.');
            abort_if($old?->is_system, 422, 'Root groups are protected. Add or edit accounts beneath them.');
            $parent = (clone $q)->where('id', $v['parent_id'])->first();
            abort_unless($parent && $parent->is_group, 422, 'Choose a group in this company as the parent.');
            abort_unless($parent->root_type === $v['root_type'], 422, 'The parent must have the same root type.');
            abort_if($v['active'] && ! $parent->active, 422, 'An active account needs an active parent group.');
            if ($old) {
                abort_if($old->account_type !== $v['account_type'] && DB::table('accounting_journal_lines')->where('account_id', $old->id)->exists(), 422, 'The type of an account referenced by journals cannot change.');
                abort_if((bool) $old->is_group !== (bool) $v['is_group'] || $old->root_type !== $v['root_type'], 422, 'Account kind and root type cannot be changed.');
                $cursor = $parent;
                $visited = [];
                while ($cursor) {
                    abort_if($cursor->id === $old->id || isset($visited[$cursor->id]), 422, 'An account cannot be its own ancestor.');
                    $visited[$cursor->id] = true;
                    $cursor = $cursor->parent_id ? (clone $q)->where('id', $cursor->parent_id)->first() : null;
                }abort_if(! $v['active'] && (clone $q)->where('parent_id', $old->id)->where('active', true)->exists(), 422, 'Deactivate the active child accounts first.');
            }
            abort_if((clone $q)->where('code', $v['code'])->when($old, fn ($query) => $query->where('id', '!=', $old->id))->exists(), 422, 'This account code is already used in this company.');
            abort_unless(in_array($v['account_type'], self::TYPES[$v['root_type']], true), 422, 'Choose an account type for this root.');
            abort_if($v['is_group'] && $v['account_type'] !== 'General', 422, 'Group accounts use the General account type.');
            $values = collect($v)->except(['id', 'version'])->all();
            $values['version'] = $old ? $old->version + 1 : 1;
            $values['updated_at'] = now();
            if ($old) {
                DB::table('accounting_accounts')->where('id', $old->id)->update($values);
                $id = $old->id;
            } else {
                $id = DB::table('accounting_accounts')->insertGetId($values + ['created_at' => now()]);
            }
            Access::audit($r, $company, $old ? 'Account updated' : 'Account created', $v['code'].' - '.$v['name'], ['account_id' => $id, 'previous' => $old ? collect((array) $old)->only(['code', 'name', 'parent_id', 'active', 'account_type', 'description'])->all() : null]);

            return DB::table('accounting_accounts')->where('id', $id)->first();
        });

        return ['message' => 'Account saved.', 'account' => $account];
    }

    public function delete(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer|min:1', 'version' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'delete');
        DB::transaction(function () use ($r, $id, $company, $v) {
            $this->lock($company);
            $account = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($account, 404);
            abort_if($account->version !== (int) $v['version'], 409, 'This account was changed by another person. Close this form and reopen the account before deleting.');
            abort_if(DB::table('accounting_journal_lines')->where('account_id', $id)->exists(), 422, 'This account is referenced by a journal. Deactivate it instead of deleting it.');
            abort_if($account->is_system, 422, 'Root groups cannot be deleted.');
            abort_if(DB::table('accounting_accounts')->where('parent_id', $id)->exists(), 422, 'Move or delete child accounts first.');
            Access::audit($r, $company, 'Account deleted', $account->code.' - '.$account->name, ['account' => (array) $account]);
            DB::table('accounting_accounts')->where('id', $id)->delete();
        });

        return ['message' => 'Account deleted.'];
    }
}
