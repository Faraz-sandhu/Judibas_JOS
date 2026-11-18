<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class DefaultAssetGroups
{
    public static function ensureNonCurrentAssets(int $company): void
    {
        DB::transaction(function () use ($company) {
            DB::table('communication_companies')->where('id', $company)->lockForUpdate()->first();
            $root = DB::table('accounting_accounts')->where('company_id', $company)
                ->where('root_type', 'Asset')->whereNull('parent_id')->where('is_group', true)->first();
            if (! $root) {
                return;
            }
            // Preserve existing groups, codes, hierarchy and activity settings.
            $exists = DB::table('accounting_accounts')->where('company_id', $company)
                ->where('root_type', 'Asset')->where('is_group', true)->get(['name'])
                ->contains(fn ($account) => preg_replace('/[^a-z]/', '', strtolower($account->name)) === 'noncurrentassets');
            if ($exists) {
                return;
            }
            DB::table('accounting_accounts')->insert([
                'company_id' => $company, 'parent_id' => $root->id,
                'code' => ChartSummary::nextCode($company, $root),
                'name' => 'Non-current Assets', 'root_type' => 'Asset',
                'account_type' => 'General', 'is_group' => true, 'is_system' => false,
                'active' => (bool) $root->active, 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
