<?php

use App\Modules\Accounting\Services\DefaultAssetGroups;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('accounting_accounts')->where('root_type', 'Asset')
            ->whereNull('parent_id')->where('is_group', true)->distinct()->pluck('company_id') as $company) {
            DefaultAssetGroups::ensureNonCurrentAssets((int) $company);
        }
    }

    public function down(): void
    {
        // Retain account records: they may have children or transactions after deployment.
    }
};
