<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyWorkspace
{
    public const PRODUCTS = ['pms' => 'projects', 'accounting' => 'accounts', 'canteen' => 'canteen', 'cv-bank' => 'cv-bank', 'daymark' => 'daymark'];

    public static function directory(): array
    {
        $r = request();
        if ($r->attributes->has('workspace.company_directory')) {
            return $r->attributes->get('workspace.company_directory');
        }
        $companies = DB::table('communication_companies')->orderBy('id')->get(['id', 'name']);
        $counts = $companies->countBy(fn ($c) => Str::slug($c->name) ?: 'company');
        $result = [];
        foreach ($companies as $company) {
            $base = Str::slug($company->name) ?: 'company';
            $slug = $counts[$base] > 1 || in_array($base, ['all', 'api', 'join', 'service-worker'], true) ? $base.'-'.$company->id : $base;
            $result[] = ['id' => (int) $company->id, 'name' => $company->name, 'slug' => $slug];
        }
        // Also disambiguate an actual company name ending in another company's numeric suffix.
        $used = [];
        foreach ($result as &$company) {
            while (isset($used[$company['slug']])) {
                $company['slug'] .= '-'.$company['id'];
            }
            $used[$company['slug']] = true;
        }
        $r->attributes->set('workspace.company_directory', $result);

        return $result;
    }

    public static function permitted(Request $r): array
    {
        if (! $r->user()?->is_active && ! PlatformAccess::administrator($r)) {
            return [];
        }
        $ids = PlatformAccess::administrator($r) ? null : CommunicationAccess::companies($r->user()->id);

        return array_values(array_filter(self::directory(), fn ($c) => $ids === null || in_array($c['id'], $ids, true)));
    }

    public static function url(string $product, int|string $id): string
    {
        if ($id === 'all') {
            return '/'.$product.'/all';
        }
        $company = collect(self::directory())->firstWhere('id', (int) $id);

        return '/'.$product.($company ? '/'.$company['slug'] : '');
    }
}
