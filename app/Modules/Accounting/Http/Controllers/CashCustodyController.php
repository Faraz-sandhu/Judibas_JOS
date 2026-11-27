<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\User;
use App\Modules\Accounting\Services\{AccountingAccess as Access, AdminRecordHistory, CashCustody, ChartSummary, Currency, ImprestFunds, Ledger, Permissions, VoucherNumber};
use App\Services\{PlatformAccess, ProductCompanyAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashCustodyController
{
    private function eligible(int $company, int $id): bool
    {
        $user = User::find($id);
        if (! $user || ! $user->is_active || ! in_array($company, ProductCompanyAccess::ids($id, 'accounts'))
            || ! DB::table('user_product_access')->where('user_id', $id)->where('product_slug', 'accounts')->exists()) return false;
        if (DB::table('accounting_imprest_funds')->where('company_id', $company)->where('user_id', $id)->exists()) return false;
        $role = DB::table('accounting_members')->where('user_id', $id)->value('role') ?? 'viewer';
        $permissions = DB::table('accounting_roles')->where('slug', $role)->where('active', true)->value('permissions');
        return (bool) $user->judibas_administrator || in_array('journals.view', json_decode($permissions ?? '[]', true));
    }

    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'page' => 'nullable|integer|min:1', 'custodian_id' => 'nullable|integer', 'activity_page' => 'nullable|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'journals.view');
        $custodians = DB::table('accounting_cash_custodians as c')->join('users as u', 'u.id', '=', 'c.user_id')->join('accounting_accounts as a', 'a.id', '=', 'c.account_id')
            ->where('c.company_id', $company)->orderBy('u.name')->get(['c.*', 'u.name', 'a.name as account_name', 'a.code', 'a.active']);
        foreach ($custodians as $c) $c->balance = Ledger::money(CashCustody::balance($c->account_id, now()->toDateString()));
        $rows = DB::table('accounting_cash_custody_transfers as t')->join('accounting_journals as j', 'j.id', '=', 't.journal_id')
            ->join('users as u', 'u.id', '=', 't.receiver_id')->join('accounting_accounts as s', 's.id', '=', 't.source_account_id')->join('accounting_accounts as d', 'd.id', '=', 't.destination_account_id')
            ->where('t.company_id', $company)->whereNotIn('j.id', AdminRecordHistory::removed('journal', $company))->orderByDesc('t.id')
            ->select(['t.*', 'j.number', 'j.status', 'j.version', 'j.posting_date', 'u.name as receiver_name', 's.name as source_name', 'd.name as destination_name'])->paginate(15);
        foreach ($rows as $row) { $row->amount = Ledger::money($row->amount_minor); $row->can_receive = ! $row->received_at && $row->status === 'draft' && ((int) $row->receiver_id === (int) $r->user()?->id || PlatformAccess::super($r)); }
        $users = User::query()->where('is_active', true);
        ProductCompanyAccess::users($users, 'accounts', [$company]);
        $users->whereExists(fn ($q) => $q->selectRaw('1')->from('user_product_access as p')->whereColumn('p.user_id', 'users.id')->where('p.product_slug', 'accounts'));
        $selected = isset($v['custodian_id']) ? $custodians->firstWhere('id', (int) $v['custodian_id']) : $custodians->first();
        abort_if(isset($v['custodian_id']) && ! $selected, 422, 'Choose a cash custodian in this company.');
        $activity = DB::table('accounting_journal_lines as l')->join('accounting_journals as j', 'j.id', '=', 'l.journal_id')
            ->where('j.company_id', $company)->where('l.account_id', $selected?->account_id ?? 0)
            ->whereIn('j.status', ['posted', 'reversed'])->where('j.posting_date', '<=', now()->toDateString())
            ->orderByDesc('j.posting_date')->orderByDesc('j.id')
            ->select(['l.id', 'j.id as journal_id', 'j.number', 'j.posting_date', 'j.status', 'j.remarks', 'l.debit_minor', 'l.credit_minor'])->paginate(15, ['*'], 'activity_page');
        foreach ($activity as $line) { $line->cash_in = Ledger::money($line->debit_minor); $line->cash_out = Ledger::money($line->credit_minor); }
        return ['custodians' => $custodians, 'transfers' => $rows, 'activity' => $activity, 'activity_custodian_id' => $selected?->id, 'users' => $users->orderBy('name')->get(['id', 'name'])->filter(fn ($u) => $this->eligible($company, $u->id))->values(), 'can_create' => Permissions::can($r, 'journals.create'), 'can_manage' => Permissions::can($r, 'journals.create') && Permissions::can($r, 'chart.create')];
    }

    public function assign(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'user_id' => 'required|integer', 'parent_id' => 'required|integer', 'name' => 'required|string|max:150']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'chart.create'); Permissions::require($r, 'journals.create');
        return DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company); Ledger::book($company);
            abort_unless($this->eligible($company, (int) $v['user_id']), 422, 'Select an active Accounting user assigned to this company.');
            abort_if(DB::table('accounting_imprest_funds')->where('company_id', $company)->where('user_id', $v['user_id'])->exists(), 422, 'A restricted disbursement officer cannot also hold an unrestricted cash custody account.');
            abort_if(DB::table('accounting_cash_custodians')->where('company_id', $company)->where('user_id', $v['user_id'])->exists(), 422, 'This person already has a cash custody account.');
            $parent = DB::table('accounting_accounts')->where('company_id', $company)->where('id', $v['parent_id'])->where('is_group', true)->where('active', true)->where('root_type', 'Asset')->first();
            abort_unless($parent && trim($v['name']) !== '', 422, 'Choose an active Asset group and enter an account name.');
            $account = DB::table('accounting_accounts')->insertGetId(['company_id' => $company, 'parent_id' => $parent->id, 'code' => ChartSummary::nextCode($company, $parent), 'name' => trim($v['name']), 'root_type' => 'Asset', 'account_type' => 'Cash', 'is_group' => false, 'is_system' => false, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('accounting_cash_custodians')->insert(['company_id' => $company, 'user_id' => $v['user_id'], 'account_id' => $account, 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $company, 'Cash custodian assigned', trim($v['name']), ['user_id' => $v['user_id'], 'account_id' => $account]);
            return ['message' => 'Cash custody account created. Record client receipts directly into this Cash account.'];
        });
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'custodian_id' => 'required|integer', 'direction' => 'required|in:issue,return', 'head_account_id' => 'required|integer', 'receiver_id' => 'required|integer', 'amount' => 'required', 'posting_date' => 'required|date_format:Y-m-d', 'request_key' => 'required|uuid', 'remarks' => 'nullable|string|max:2000']);
        $company = (int) $v['company_id']; Access::company($r, $company, 'journals.create');
        return DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company); $book = Ledger::book($company);
            $future = $v['posting_date'] > now()->toDateString();
            abort_if($future && ! PlatformAccess::super($r), 403, 'Only Super Admin can create a post-dated voucher.');
            Ledger::checkDate($book, $v['posting_date'], $future);
            $c = DB::table('accounting_cash_custodians')->where('company_id', $company)->find($v['custodian_id']);
            abort_unless($c, 422, 'Choose a cash custodian in this company.');
            abort_unless($this->eligible($company, (int) $v['receiver_id']), 422, 'The receiver must be an active Accounting user assigned to this company.');
            abort_if($v['direction'] === 'issue' && (int) $v['receiver_id'] !== (int) $c->user_id, 422, 'The assigned custodian must receive this cash issue.');
            abort_if($v['direction'] === 'return' && (int) $v['receiver_id'] === (int) $c->user_id, 422, 'Choose a Head Office receiver other than the returning custodian.');
            $head = DB::table('accounting_accounts')->where('company_id', $company)->find($v['head_account_id']);
            $cash = DB::table('accounting_accounts')->where('company_id', $company)->find($c->account_id);
            foreach ([$head, $cash] as $a) abort_unless($a && $a->active && ! $a->is_group && $a->account_type === 'Cash' && $a->root_type === 'Asset' && (! $a->currency || $a->currency === $book->currency) && ! ImprestFunds::protectedAccount($a->id), 422, 'Use active base-currency Cash accounts outside restricted disbursement funds.');
            abort_if(DB::table('accounting_cash_custodians')->where('account_id', $head->id)->exists(), 422, 'Choose a Head Office Cash account outside individual custody accounts.');
            $source = $v['direction'] === 'issue' ? $head->id : $cash->id;
            $destination = $v['direction'] === 'issue' ? $cash->id : $head->id;
            $amount = Ledger::minor($v['amount']); abort_unless($amount > 0, 422, 'Enter an amount greater than zero.');
            $old = DB::table('accounting_journals')->where('company_id', $company)->where('request_key', $v['request_key'])->first();
            if ($old) {
                $t = CashCustody::transfer($old->id);
                abort_unless($t && (int) $t->custodian_id === (int) $c->id && $t->direction === $v['direction'] && (int) $t->source_account_id === (int) $source && (int) $t->destination_account_id === (int) $destination && (int) $t->receiver_id === (int) $v['receiver_id'] && (int) $t->amount_minor === $amount && $old->posting_date === $v['posting_date'] && $old->remarks === trim($v['remarks'] ?? ''), 409, 'This request key belongs to a different voucher.');
                AdminRecordHistory::assertLive('journal', $company, $old->id);
                return ['message' => 'Existing custody voucher returned.', 'journal' => Ledger::document($company, $old->id)];
            }
            $lines = Ledger::lines($company, [['account_id' => $destination, 'debit' => Ledger::money($amount), 'credit' => '0.00'], ['account_id' => $source, 'debit' => '0.00', 'credit' => Ledger::money($amount)]]);
            Ledger::validateCashVoucher($company, 'contra', $lines, 'Cash custody transfer');
            Ledger::requireCashFunds($company, 'contra', $v['posting_date'], $lines);
            foreach ([$head, $cash] as $a) Currency::account($a, $book->currency, $company);
            $id = DB::table('accounting_journals')->insertGetId(['company_id' => $company, 'request_key' => $v['request_key'], 'number' => VoucherNumber::next($company, 'CCV'), 'kind' => 'contra', 'posting_date' => $v['posting_date'], 'currency' => $book->currency, 'exchange_rate' => '1.000000', 'voucher_person' => 'Cash custody '.($v['direction'] === 'issue' ? 'issue' : 'handover'), 'remarks' => trim($v['remarks'] ?? ''), 'prepared_by' => $r->user()?->id, 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('accounting_journal_lines')->insert(array_map(fn ($l) => $l + ['journal_id' => $id], $lines));
            DB::table('accounting_cash_custody_transfers')->insert(['company_id' => $company, 'journal_id' => $id, 'custodian_id' => $c->id, 'direction' => $v['direction'], 'source_account_id' => $source, 'destination_account_id' => $destination, 'receiver_id' => $v['receiver_id'], 'amount_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $company, 'Cash custody voucher created', 'Journal #'.$id, ['amount' => Ledger::money($amount), 'direction' => $v['direction']]);
            return ['message' => 'Non-posted custody voucher saved. The receiver must confirm cash receipt before Super Admin posting.', 'journal' => Ledger::document($company, $id)];
        });
    }

    public function receive(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer', 'note' => 'required|string|min:5|max:500']);
        abort_if(mb_strlen(trim($v['note'])) < 5, 422, 'Enter a receiving note of at least five characters.');
        $company = (int) $v['company_id']; Access::company($r, $company, 'journals.view');
        return DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            $t = DB::table('accounting_cash_custody_transfers')->where('company_id', $company)->where('journal_id', $id)->first(); abort_unless($t, 404);
            abort_unless((int) $r->user()?->id === (int) $t->receiver_id || PlatformAccess::super($r), 403, 'Only the designated receiver or Super Admin can confirm this handover.');
            AdminRecordHistory::assertLive('journal', $company, $id);
            $j = DB::table('accounting_journals')->find($id);
            if ($t->received_at) return ['message' => 'Cash receipt already confirmed.'];
            abort_unless($j->status === 'draft' && (int) $j->version === (int) $v['version'], 409, 'The voucher changed. Reopen it before confirming receipt.');
            DB::table('accounting_cash_custody_transfers')->where('id', $t->id)->update(['received_at' => now(), 'received_by' => $r->user()?->id, 'receipt_note' => trim($v['note']), 'updated_at' => now()]);
            Access::audit($r, $company, 'Cash custody receipt confirmed', $j->number, ['note' => trim($v['note']), 'receiver_id' => $t->receiver_id, 'confirmed_on_behalf' => (int) $r->user()?->id !== (int) $t->receiver_id]);
            return ['message' => 'Physical receipt confirmed. Ledger balances change only after Super Admin posting.'];
        });
    }
}
