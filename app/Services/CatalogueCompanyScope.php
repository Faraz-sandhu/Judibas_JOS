<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Companies shown by a catalogue card are also the boundary for its All action. */
class CatalogueCompanyScope
{
    public static function groups(string $product): array
    {
        return array_values(array_filter(config('product-catalogue.groups', []), fn ($g) => $g['slug'] === $product));
    }

    public static function ids(string $product, Request $r): array
    {
        $groups = self::groups($product);
        $group = $r->query('workspace_group');
        abort_unless($group === null || is_string($group), 422, 'Choose a valid product group.');
        $key = 'catalogue.company_ids.'.$product.'.'.($group ?: 'all');
        if ($r->attributes->has($key)) {
            return $r->attributes->get($key);
        }
        if ($group !== null && $group !== '') {
            abort_unless(is_string($group), 422, 'Choose a valid product group.');
            $groups = array_values(array_filter($groups, fn ($g) => $g['id'] === $group));
            abort_unless(count($groups), 422, 'This company group does not belong to the selected product.');
        }
        $names = [];
        foreach ($groups as $g) {
            foreach ($g['companies'] as $name) {
                $names[] = mb_strtolower($name);
            }
        }
        $ids = DB::table('communication_companies')->whereIn(DB::raw('LOWER(name)'), array_unique($names))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $r->attributes->set($key, $ids);

        return $ids;
    }

    public static function label(string $product, Request $r): ?string
    {
        $group = $r->query('workspace_group');

        return collect(self::groups($product))->firstWhere('id', $group)['name'] ?? null;
    }

    public static function link(string $url, Request $r, string $product = 'accounts'): string
    {
        $group = $r->query('workspace_group');
        if (! $group) {
            return $url;
        }
        self::ids($product, $r);
        $parts = explode('#', $url, 2);
        $parts[0] .= (str_contains($parts[0], '?') ? '&' : '?').'workspace_group='.rawurlencode($group);

        return $parts[0].(isset($parts[1]) ? '#'.$parts[1] : '');
    }
}
