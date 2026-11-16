<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\ChartSummary;
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
        if($r->boolean('choose'))return ['can_view_all'=>\App\Services\PlatformAccess::administrator($r),'is_super_admin'=>\App\Services\PlatformAccess::super($r),'companies'=>$companies,'company_id'=>null,'accounts'=>[],'book'=>null,'permissions'=>\App\Modules\Accounting\Services\Permissions::effective($r),'role'=>$role,'account_types'=>self::TYPES];
        if($r->input('company_id')==='all'){
            AllCompaniesController::authorize($r);
            return ['all_companies'=>true,'can_view_all'=>true,'companies'=>$companies,'company_id'=>null,'accounts'=>[],'book'=>null,'permissions'=>\App\Modules\Accounting\Services\Permissions::effective($r),'role'=>$role,'role_name'=>'Administrator','can_manage'=>false,'can_delete'=>false,'account_types'=>self::TYPES];
        }
        $r->validate(['company_id' => 'nullable|integer|min:1']);
        $company = (int) ($r->input('company_id') ?: ($companies->first()->id ?? 0));
        if ($company) {
            Access::company($r, $company);
        }

        $book=$company?DB::table('accounting_books')->where('company_id',$company)->first():null;
        if($book)$book=\App\Modules\Accounting\Services\GlobalPolicy::apply($book);
        if($book)$book->approval_types=\App\Modules\Accounting\Services\ApprovalPolicy::selected($book);
        return ['can_view_all'=>\App\Services\PlatformAccess::administrator($r), 'global_policy'=>$book, 'is_super_admin'=>\App\Services\PlatformAccess::super($r), 'companies' => $companies, 'company_id' => $company ?: null, 'role' => $role, 'book' => $book, 'permissions' => \App\Modules\Accounting\Services\Permissions::effective($r), 'role_name' => DB::table('accounting_roles')->where('slug', $role)->value('name') ?? 'Accounting Manager', 'can_manage' => count(array_filter(\App\Modules\Accounting\Services\Permissions::effective($r), fn ($p) => preg_match('/\.(create|update|post)$/', $p))) > 0, 'can_delete' => \App\Modules\Accounting\Services\Permissions::can($r, 'chart.delete'), 'account_types' => self::TYPES, 'accounts' => $company ? (\App\Modules\Accounting\Services\Permissions::can($r, 'chart.view') ? ChartSummary::accounts($company) : DB::table('accounting_accounts')->where('company_id', $company)->orderBy('code')->get(['id','company_id','parent_id','code','name','root_type','account_type','is_group','is_system','active','version','description','currency'])) : []];
    }

    private function lock(int $company): void
    {
        DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first();
    }

    public function initialize(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'chart.initialize');
        DB::transaction(function () use ($r, $company) {
            $this->lock($company);
            abort_if(DB::table('accounting_accounts')->where('company_id', $company)->exists(), 422, 'This company already has a chart of accounts.');
            foreach (['Asset' => ['01', 'Assets', [['01-01', 'Current Assets', [['01-01-01', 'Cash', 'Cash'], ['01-01-02', 'Bank', 'Bank'], ['01-01-03', 'Accounts Receivable', 'Receivable']]]]], 'Liability' => ['02', 'Liabilities', [['02-01', 'Current Liabilities', [['02-01-01', 'Accounts Payable', 'Payable']]]]], 'Equity' => ['03', 'Equity', [['03-01', 'Owner Capital', 'Capital']]], 'Income' => ['04', 'Income', [['04-01', 'Service Revenue', 'Sales']]], 'Expense' => ['05', 'Expenses', [['05-01', 'Operating Expenses', [['05-01-01', 'Office Expenses', 'Operating Expense']]]]]] as $root => $spec) {
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
        $v = $r->validate(['version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'id' => 'nullable|integer|min:1', 'company_id' => 'required|integer|min:1', 'parent_id' => 'required|integer|min:1', 'code' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]*$/'], 'name' => 'required|string|max:150', 'root_type' => ['required', Rule::in(array_keys(self::TYPES))], 'account_type' => 'required|string', 'is_group' => 'required|boolean', 'active' => 'required|boolean', 'description' => 'nullable|string|max:2000','currency'=>'nullable|string|size:3']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, $r->filled('id') ? 'chart.update' : 'chart.create');
        $v['code'] = strtoupper(trim($v['code'] ?? ''));
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
            if ($v['code'] === '') {
                $v['code'] = $old ? $old->code : ChartSummary::nextCode($company, $parent);
            }
            abort_if((clone $q)->where('code', $v['code'])->when($old, fn ($query) => $query->where('id', '!=', $old->id))->exists(), 422, 'This account code is already used in this company.');
            abort_unless(in_array($v['account_type'], self::TYPES[$v['root_type']], true), 422, 'Choose an account type for this root.');
            abort_if($v['is_group'] && $v['account_type'] !== 'General', 422, 'Group accounts use the General account type.');
            $currency=$v['currency']??null;$base=DB::table('accounting_books')->where('company_id',$company)->value('currency')??'PKR';
            if($currency===$base)$currency=null;
            abort_if($currency&&(!in_array($currency,\App\Modules\Accounting\Services\Currency::CODES,true)||$v['is_group']||!in_array($v['account_type'],['Cash','Bank'])),422,'Foreign account currency is supported on cash/bank ledger accounts only.');
            abort_if($old&&($old->currency??null)!==$currency&&(DB::table('accounting_journal_lines')->where('account_id',$old->id)->exists()||DB::table('accounting_invoices')->where('control_account_id',$old->id)->exists()),422,'An account currency cannot change after it is used.');
            $v['currency']=$currency;
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
        Access::company($r, $company, 'chart.delete');
        DB::transaction(function () use ($r, $id, $company, $v) {
            $this->lock($company);
            $account = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($account, 404);
            abort_if($account->version !== (int) $v['version'], 409, 'This account was changed by another person. Close this form and reopen the account before deleting.');
            abort_if(DB::table('accounting_journal_lines')->where('account_id', $id)->exists(), 422, 'This account is referenced by a journal. Deactivate it instead of deleting it.');
            abort_if(DB::table('accounting_cash_basis_review_lines')->where('company_id', $company)->where('account_id', $id)->exists(), 422, 'This account is used in a cash-basis allocation. Deactivate it instead of deleting it.');
            abort_if($account->is_system, 422, 'Root groups cannot be deleted.');
            abort_if(DB::table('accounting_accounts')->where('parent_id', $id)->exists(), 422, 'Move or delete child accounts first.');
            Access::audit($r, $company, 'Account deleted', $account->code.' - '.$account->name, ['account' => (array) $account]);
            DB::table('accounting_accounts')->where('id', $id)->delete();
        });

        return ['message' => 'Account deleted.'];
    }
}
