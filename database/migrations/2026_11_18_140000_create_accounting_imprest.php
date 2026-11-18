<?php

use App\Modules\Accounting\Services\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_imprest_funds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('account_id')->unique()->constrained('accounting_accounts')->restrictOnDelete();
            $t->string('name', 150);
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
        });
        Schema::create('accounting_imprest_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fund_id')->constrained('accounting_imprest_funds')->restrictOnDelete();
            $t->foreignId('journal_id')->unique()->constrained('accounting_journals')->restrictOnDelete();
            $t->string('type', 12);
            $t->boolean('void')->default(false);
            $t->timestamps();
        });
        foreach (Permissions::presets() as $slug => [$name, $keys]) {
            $role = DB::table('accounting_roles')->where('slug', $slug)->first();
            if ($role && $role->protected) {
                $new = array_values(array_filter($keys, fn ($p) => str_starts_with($p, 'imprest.')));
                DB::table('accounting_roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_unique(array_merge(json_decode($role->permissions, true), $new)))),
                    'version' => $role->version + 1, 'updated_at' => now(),
                ]);
            } elseif (! $role && $slug === 'junior_finance_officer') {
                DB::table('accounting_roles')->insert(['slug' => $slug, 'name' => $name, 'permissions' => json_encode($keys), 'protected' => true, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Financial custody and journal links must not be removed by a rollback.
    }
};
