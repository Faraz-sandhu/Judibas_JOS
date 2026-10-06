<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PortalContent
{
    public static function products(bool $includeHidden = false): array
    {
        $overrides = DB::table('product_settings')->get()->keyBy('slug');
        $products = collect(config('judibas.products'))->map(function ($p, $index) use ($overrides) {
            $o = $overrides->get($p['slug']);
            $p['visible'] = $o ? (bool) $o->visible : true;
            $p['display_order'] = $o ? $o->display_order : $index;
            if ($o) {
                foreach (['name', 'description', 'icon', 'color'] as $key) {
                    $p[$key] = $o->$key;
                }
            }

            return $p;
        });

        return $products->filter(fn ($p) => $includeHidden || $p['visible'])->sortBy('display_order')->values()->all();
    }

    public static function pages(): array
    {
        $pages = config('pages');
        foreach (DB::table('site_pages')->get() as $p) {
            $pages[$p->slug] = ['title' => $p->title, 'intro' => $p->intro, 'body' => $p->body];
        }

return $pages;
    }

    public static function announcement(): array
    {
        $row = DB::table('site_content')->where('key', 'announcement')->first();

        return $row ? json_decode($row->value, true) : ['title' => 'Workspace', 'text' => 'Your company tools, together', 'url' => '/about'];
    }
}
