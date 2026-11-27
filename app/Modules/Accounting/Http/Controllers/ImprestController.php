<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\User;
use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\ApprovalFlow;
use App\Modules\Accounting\Services\ChartSummary;
use App\Modules\Accounting\Services\ExpenseVoucherHours;
use App\Modules\Accounting\Services\ImprestFunds;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Permissions;
use App\Services\ProductCompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImprestController
{
    public function index(Request $r)
    {
        $company = (int) $r->validate(['company_id' => 'required|integer', 'page' => 'nullable|integer|min:1'])['company_id'];
        Access::company($r, $company, 'imprest.view');
        $manage = Permissions::can($r, 'imprest.manage');
        $funds = DB::table('accounting_imprest_funds as f')->join('users as u', 'u.id', '=', 'f.user_id')->where('f.company_id', $company)
            ->when(! $manage && ! Permissions::can($r, 'imprest.post'), fn ($q) => $q->where('f.user_id', $r->user()?->id))
            ->orderBy('u.name')->get(['f.*', 'u.name as officer_name', 'u.email as officer_email']);
        foreach ($funds as $fund) {
            $fund->balance = Ledger::money(ImprestFunds::balance($fund));
            $fund->reserved = Ledger::money(ImprestFunds::reserved($fund));
            $fund->available = Ledger::money(ImprestFunds::available($fund, now()->toDateString()));
        }
        $movements = DB::table('accounting_imprest_movements as m')->join('accounting_journals as j', 'j.id', '=', 'm.journal_id')
            ->join('accounting_imprest_funds as f', 'f.id', '=', 'm.fund_id')->join('users as u', 'u.id', '=', 'f.user_id')
            ->whereIn('m.fund_id', $funds->pluck('id'))->whereNotIn('j.id',\App\Modules\Accounting\Services\AdminRecordHistory::removed('journal',$company))->orderByDesc('j.id')
            ->select(['m.id', 'm.type', 'm.void', 'j.id as journal_id', 'j.number', 'j.posting_date', 'j.status', 'j.version', 'j.remarks', 'j.voucher_person', 'f.name as fund_name', 'u.name as officer_name'])->paginate(15);
        $movements->setCollection($movements->getCollection()->map(function ($m) {
            $m->amount = Ledger::money((int) DB::table('accounting_journal_lines')->where('journal_id', $m->journal_id)->sum('debit_minor'));
            $m->approval = ApprovalFlow::approval((int) DB::table('accounting_journals')->where('id', $m->journal_id)->value('company_id'), 'journal', $m->journal_id);

            return $m;
        }));
        $officers = [];
        if ($manage) {
            $q = User::query()->where('is_active', true)->where('judibas_administrator', false)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_product_access as p')->whereColumn('p.user_id', 'users.id')->where('p.product_slug', 'accounts'));
            ProductCompanyAccess::users($q, 'accounts', [$company]);
            $officers = $q->join('accounting_members as am', 'am.user_id', '=', 'users.id')->join('accounting_roles as ar', 'ar.slug', '=', 'am.role')->where('ar.active', true)->whereJsonContains('ar.permissions', 'imprest.create')->orderBy('users.name')->get(['users.id', 'users.name', 'users.email']);
        }

        return ['funds' => $funds, 'movements' => $movements, 'officers' => $officers, 'can_manage' => $manage, 'can_post' => Permissions::can($r, 'imprest.post'), 'can_create' => Permissions::can($r, 'imprest.create'), 'restricted' => ImprestFunds::restricted($r)];
    }

    public function createFund(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'user_id' => 'required|integer', 'parent_id' => 'required|integer', 'name' => 'required|string|max:150']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'imprest.manage');

        return DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            Ledger::book($company);
            $user = User::find($v['user_id']);
            abort_unless($user && $user->is_active && ! $user->judibas_administrator && ProductCompanyAccess::ids($user->id, 'accounts') && in_array($company, ProductCompanyAccess::ids($user->id, 'accounts')) && DB::table('user_product_access')->where('user_id', $user->id)->where('product_slug', 'accounts')->exists(), 422, 'Select an active Accounting user assigned to this company.');
            $role = DB::table('accounting_roles')->where('slug', DB::table('accounting_members')->where('user_id', $user->id)->value('role'))->where('active', true)->first();
            abort_unless($role && in_array('imprest.create', json_decode($role->permissions, true)), 422, 'Assign the Junior Finance Officer role or a role with Imprest Create permission before creating this fund.');
            abort_if($r->user()?->id === $user->id, 422, 'A fund manager cannot assign a restricted fund to themselves.');
            abort_if(DB::table('accounting_imprest_funds')->where('company_id', $company)->where('user_id', $user->id)->exists(), 422, 'This officer already has a fund in this company. Top up the existing fund.');
            $parent = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $v['parent_id'])->where('is_group', true)->where('active', true)->where('root_type', 'Asset')->first();
            abort_unless($parent, 422, 'Choose an active Asset group for the officer cash account.');
            $name = trim($v['name']);
            abort_if($name === '', 422, 'Enter the fund name.');
            $account = DB::table('accounting_accounts')->insertGetId(['company_id' => $company, 'parent_id' => $parent->id, 'code' => ChartSummary::nextCode($company, $parent), 'name' => $name, 'root_type' => 'Asset', 'account_type' => 'Cash', 'is_group' => false, 'is_system' => false, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('accounting_imprest_funds')->insertGetId(['company_id' => $company, 'user_id' => $user->id, 'account_id' => $account, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $company, 'Imprest fund assigned', $name, ['fund_id' => $id, 'officer_id' => $user->id, 'account_id' => $account]);

            return ['message' => 'Fund created. This officer can now create vouchers only from their assigned fund in this company. Post an advance voucher to make funds available.', 'fund_id' => $id];
        });
    }

    public function show(Request $r, int $id)
    {
        $company = (int) $r->validate(['company_id' => 'required|integer'])['company_id'];
        Access::company($r, $company, 'imprest.view');
        $fund = DB::table('accounting_imprest_movements as m')->join('accounting_imprest_funds as f', 'f.id', '=', 'm.fund_id')
            ->where('m.journal_id', $id)->where('f.company_id', $company)->select('f.*')->first();
        abort_unless($fund, 404);
        abort_unless(Permissions::can($r, 'imprest.manage') || Permissions::can($r, 'imprest.post') || $fund->user_id === $r->user()?->id, 403, 'This voucher belongs to another officer.');

        return ['journal' => Ledger::document($company, $id)];
    }

    public function allocate(Request $r)
    {
        return $this->draft($r, 'funding');
    }

    public function spend(Request $r)
    {
        return $this->draft($r, 'spending');
    }

    private function draft(Request $r, string $type)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'fund_id' => 'required|integer', 'account_id' => 'required|integer', 'amount' => 'required', 'posting_date' => 'required|date_format:Y-m-d', 'request_key' => 'required|uuid', 'person' => 'required|string|max:150', 'remarks' => 'nullable|string|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, $type === 'funding' ? 'imprest.manage' : 'imprest.create');

        return DB::transaction(function () use ($r, $v, $company, $type) {
            Ledger::lock($company);
            $book = Ledger::book($company);
            $futureVoucher=$v['posting_date']>now()->toDateString();
            abort_if($futureVoucher&&!\App\Services\PlatformAccess::super($r),403,'Only Super Admin can create or change a post-dated voucher.');
            Ledger::checkDate($book, $v['posting_date'], $futureVoucher);
            $fund = DB::table('accounting_imprest_funds')->where('company_id', $company)->find($v['fund_id']);
            abort_unless($fund && $fund->active, 422, 'Choose an active fund in this company.');
            if ($type === 'spending' && ! Permissions::can($r, 'imprest.manage')) {
                abort_unless($r->user()?->id === $fund->user_id, 403, 'This fund belongs to another officer.');
            }
            $amount = Ledger::minor($v['amount']);
            abort_if($amount === 0, 422, 'Enter an amount greater than zero.');
            $person = trim($v['person']);
            abort_if($person === '', 422, 'Enter the payment purpose or recipient.');
            $account = DB::table('accounting_accounts')->where('company_id', $company)->find($v['account_id']);
            abort_unless($account && $account->active && ! $account->is_group && ! $account->currency && ! ImprestFunds::protectedAccount($account->id), 422, 'Choose an active base-currency account outside officer funds.');
            abort_unless($type === 'funding' ? ($account->root_type === 'Asset' && in_array($account->account_type, ['Cash', 'Bank'])) : $account->root_type === 'Expense', 422, $type === 'funding' ? 'Choose the company Cash or Bank source account.' : 'Choose an Expense account.');
            $debit = $type === 'funding' ? $fund->account_id : $account->id;
            $credit = $type === 'funding' ? $account->id : $fund->account_id;
            $lines = Ledger::lines($company, [['account_id' => $debit, 'debit' => Ledger::money($amount), 'credit' => '0.00'], ['account_id' => $credit, 'debit' => '0.00', 'credit' => Ledger::money($amount)]]);
            $old = DB::table('accounting_journals')->where('company_id', $company)->where('request_key', $v['request_key'])->first();
            if ($old) {
                $m = ImprestFunds::movement($old->id);
                $saved = DB::table('accounting_journal_lines')->where('journal_id', $old->id)->orderBy('position')->get();
                abort_unless($m && $m->fund_id === $fund->id && $m->type === $type && ! $m->void && $old->posting_date === $v['posting_date'] && $old->voucher_person === $person && $old->remarks === trim($v['remarks'] ?? '') && $saved->count() === 2 && $saved[0]->account_id === $debit && $saved[1]->account_id === $credit && (int) $saved[0]->debit_minor === $amount, 409, 'This request key was already used for another voucher.');

                return ['message' => 'Saved voucher returned.', 'journal' => Ledger::document($company, $old->id)];
            }
            if ($type === 'spending') {
                ExpenseVoucherHours::enforce($r, $book, 'cash_payment', $lines);
                ImprestFunds::requireAvailable($fund, $v['posting_date'], $amount);
            } else {
                Ledger::requireCashFunds($company, 'cash_payment', $v['posting_date'], $lines);
                $balance = (int) DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')->where('l.account_id', $account->id)->whereIn('j.status', ['posted', 'reversed'])->where('j.posting_date', '<=', $v['posting_date'])->selectRaw('COALESCE(SUM(l.debit_minor-l.credit_minor),0) as balance')->value('balance');
                $pending = (int) DB::table('accounting_imprest_movements as m')->join('accounting_journals as j', 'j.id', '=', 'm.journal_id')->join('accounting_journal_lines as l', 'l.journal_id', '=', 'j.id')->where('m.type', 'funding')->where('m.void', false)->where('j.status', 'draft')->where('l.account_id', $account->id)->sum('l.credit_minor');
                abort_if($amount > $balance - $pending, 422, 'Company source funds are insufficient after pending imprest advances.');
            }
            $id = DB::table('accounting_journals')->insertGetId(['company_id' => $company, 'request_key' => $v['request_key'], 'number' => \App\Modules\Accounting\Services\VoucherNumber::next($company,$type==='funding'?'IAV':'PCV'), 'posting_date' => $v['posting_date'], 'currency' => $book->currency, 'exchange_rate' => '1.000000', 'kind' => $type === 'funding' ? 'general' : 'cash_payment', 'voucher_person' => $person, 'remarks' => trim($v['remarks'] ?? ''), 'prepared_by' => $r->user()?->id, 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('accounting_journal_lines')->insert(array_map(fn ($l) => $l + ['journal_id' => $id], $lines));
            DB::table('accounting_imprest_movements')->insert(['fund_id' => $fund->id, 'journal_id' => $id, 'type' => $type, 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $company, $type === 'funding' ? 'Imprest advance drafted' : 'Petty cash expense drafted', 'Journal #'.$id, ['fund_id' => $fund->id, 'officer_id' => $fund->user_id]);

            return ['message' => $type === 'funding' ? 'Advance saved as non-posted. Post it after the required approval to fund the officer.' : 'Expense saved as non-posted. Its amount is reserved from your assigned fund.', 'journal' => Ledger::document($company, $id)];
        });
    }

    public function post(Request $r, int $id)
    {
        abort_unless(\App\Services\PlatformAccess::super($r),403,'Only Super Admin can post vouchers.');
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'imprest.post');

        return DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            abort_unless(ImprestFunds::movement($id), 404);
            $j = DB::table('accounting_journals')->where('company_id', $company)->find($id);
            abort_unless($j, 404);
            if ($j->status === 'posted') {
                return ['message' => 'Imprest voucher already posted.', 'journal' => Ledger::document($company, $id)];
            }
            abort_unless($j->version === (int) $v['version'], 409, 'This voucher changed. Reload before posting.');
            ApprovalFlow::requirePosting($r, $company, 'journal', $id, $j->version);
            Ledger::post($r, $company, $j);

            return ['message' => 'Imprest voucher posted.', 'journal' => Ledger::document($company, $id)];
        });
    }

    public function cancel(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        abort_unless(Permissions::can($r, 'imprest.create') || Permissions::can($r, 'imprest.manage'), 403);

        return DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            $m = ImprestFunds::movement($id);
            abort_unless($m, 404);
            $fund = DB::table('accounting_imprest_funds')->where('company_id', $company)->find($m->fund_id);
            abort_unless($fund, 404);
            abort_unless(Permissions::can($r, 'imprest.manage') || ($fund->user_id === $r->user()?->id && $m->type === 'spending'), 403);
            if ($m->void) {
                return ['message' => 'Draft already cancelled.'];
            }
            $j = DB::table('accounting_journals')->find($id);
            abort_unless($j->status === 'draft' && $j->version === (int) $v['version'],409,'Only the latest non-posted voucher can be cancelled.');
            ApprovalFlow::ensureEditable($company,'journal',$id);
            DB::table('accounting_imprest_movements')->where('id',$m->id)->update(['void' => true, 'updated_at' => now()]);
            Access::audit($r,$company,'Imprest draft cancelled',$j->number,['fund_id' => $fund->id]);

            return ['message' => 'Draft cancelled. Its reserved amount is available again.'];
        });
    }
}
