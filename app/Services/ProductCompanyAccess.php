<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProductCompanyAccess
{
    public static function legacyIds(int $user): array
    {
        return array_values(array_unique(array_map('intval', array_filter([
            DB::table('users')->where('id', $user)->value('company_id'),
            ...DB::table('communication_company_memberships')->where('user_id', $user)->pluck('company_id')->all(),
        ]))));
    }

    public static function ids(int $user, string $product): array
    {
        $row = DB::table('user_product_access')->where('user_id', $user)->where('product_slug', $product)->first();
        return $row?->company_ids !== null ? array_values(array_map('intval', json_decode($row->company_ids, true) ?: [])) : self::legacyIds($user);
    }

    /** All product memberships, for the central employee editor rather than a product workspace. */
    public static function allIds(int $user): array
    {
        $ids = [];$legacy = null;
        $rows = DB::table('user_product_access')->where('user_id', $user)->get();
        if ($rows->isEmpty()) return self::legacyIds($user);
        foreach ($rows as $row) {
            $ids = array_merge($ids, $row->company_ids !== null ? json_decode($row->company_ids, true) ?: [] : ($legacy ??= self::legacyIds($user)));
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function selection(array $access, string $product): array
    {
        return $access['product_company_ids'][$product] ?? $access['company_ids'] ?? [];
    }

    /** SQL boundary used for employee directories/assignees, including legacy memberships. */
    public static function users($query, string $product, array $companies, string $column = 'users.id'): void
    {
        $query->where(function ($q) use ($product, $companies, $column) {
            $q->whereExists(function ($a) use ($product, $companies, $column) {
                $a->selectRaw('1')->from('user_product_access as product_scope')->whereColumn('product_scope.user_id', $column)
                    ->where('product_scope.product_slug', $product)->whereNotNull('product_scope.company_ids')
                    ->where(function ($ids) use ($companies) {
                        if (! $companies) $ids->whereRaw('1=0');
                        foreach ($companies as $id) $ids->orWhereJsonContains('product_scope.company_ids', (int) $id);
                    });
            })->orWhere(function ($legacy) use ($product, $companies, $column) {
                $legacy->whereNotExists(fn ($a) => $a->selectRaw('1')->from('user_product_access as product_scope')
                    ->whereColumn('product_scope.user_id', $column)->where('product_scope.product_slug', $product)->whereNotNull('product_scope.company_ids'))
                    ->whereIn($column, DB::table('users as legacy_person')->where(fn ($u) => $u->whereIn('legacy_person.company_id', $companies)
                        ->orWhereIn('legacy_person.id', DB::table('communication_company_memberships')->whereIn('company_id', $companies)->select('user_id')))->select('legacy_person.id'));
            });
        });
    }
}
