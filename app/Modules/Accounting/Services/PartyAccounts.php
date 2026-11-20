<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class PartyAccounts
{
    public static function sync(int $company, int $id): ?int
    {
        Ledger::lock($company);
        $party = DB::table('accounting_parties')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first();
        abort_unless($party, 422, 'Contact not found in this company.');
        if ($party->account_id) {
            DB::table('accounting_accounts')->where('company_id', $company)->where('id', $party->account_id)->update(['name' => $party->name, 'active' => $party->active, 'updated_at' => now()]);

            return $party->account_id;
        }
        $customer = $party->kind === 'customer';
        $root = $customer ? 'Asset' : 'Liability';
        $type = $customer ? 'Receivable' : 'Payable';
        $parent = DB::table('accounting_accounts')->where('company_id', $company)->where('root_type', $root)->where('is_group', true)->whereNotNull('parent_id')->orderBy('id')->first();
        if (! $parent) {
            return null;
        } // Contacts may exist before chart setup.
        $name = $customer ? 'Customer Receivables' : 'Supplier Payables';
        $group = DB::table('accounting_accounts')->where('company_id', $company)->where('parent_id', $parent->id)->where('name', $name)->where('is_group', true)->first();
        if (! $group) {
            $groupId = DB::table('accounting_accounts')->insertGetId(['company_id' => $company, 'parent_id' => $parent->id, 'code' => ChartSummary::nextCode($company, $parent), 'name' => $name, 'root_type' => $root, 'account_type' => 'General', 'is_group' => true, 'is_system' => false, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $group = DB::table('accounting_accounts')->find($groupId);
        }
        $account = DB::table('accounting_accounts')->insertGetId(['company_id' => $company, 'parent_id' => $group->id, 'code' => ChartSummary::nextCode($company, $group), 'name' => $party->name, 'root_type' => $root, 'account_type' => $type, 'is_group' => false, 'is_system' => false, 'active' => $party->active, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounting_parties')->where('id', $id)->update(['account_id' => $account]);

        return $account;
    }

    public static function protected(int $account): bool
    {
        return DB::table('accounting_parties')->where('account_id', $account)->exists();
    }

    public static function backfill(int $company): void
    {
        foreach (DB::table('accounting_parties')->where('company_id', $company)->pluck('id') as $id) {
            self::sync($company,$id);
        }
    }
}
