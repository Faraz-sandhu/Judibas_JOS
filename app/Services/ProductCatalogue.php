<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogue
{
    public const THEMES = ['workspace', 'directory', 'classic', 'disclosure'];

    public static function theme(): string
    {
        $theme = json_decode(DB::table('site_content')->where('key', 'products_theme')->value('value') ?? 'null', true);

        return in_array($theme, self::THEMES, true) ? $theme : 'classic';
    }

    public static function groups(Request $request, array $products): array
    {
        $companies = DB::table('communication_companies')->orderBy('name');
        $admin = PlatformAccess::administrator($request);
        if (! $admin) {
            $companies->whereIn('id', $request->user()?->is_active ? CommunicationAccess::companies($request->user()->id) : []);
        }
        $companies = $companies->get(['id', 'name'])->keyBy(fn ($c) => strtolower($c->name));
        $allowed = collect($products)->keyBy('slug');
        $groups = [];
        foreach (config('product-catalogue.groups') as $definition) {
            if (! $allowed->has($definition['slug']) && ! ($admin && $definition['planned'])) {
                continue;
            }
            $items = [];
            foreach ($definition['companies'] as $name) {
                $company = $companies->get(strtolower($name));
                if (! $company) {
                    continue;
                }
                $href = null;
                if (! $definition['planned'] && $definition['slug'] === 'accounts') {
                    $href = CompanyWorkspace::url('accounting',$company->id).'?workspace_group='.$definition['id'];
                }
                if (! $definition['planned'] && $definition['slug'] === 'projects') {
                    $href = CompanyWorkspace::url('pms',$company->id);
                }
                $items[] = ['name' => $company->name, 'href' => $href];
            }
            $all = ! $definition['planned'] ? ($definition['slug'] === 'accounts' ? ($admin ? '/accounting/all?workspace_group='.$definition['id'] : '/accounting?workspace_group='.$definition['id']) : '/pms/all') : null;
            if (! $admin && ! count($items)) {
                continue;
            }
            $groups[] = ['id' => $definition['id'], 'name' => $definition['name'], 'slug' => $definition['slug'], 'description' => $definition['description'], 'planned' => $definition['planned'], 'companies' => $items, 'allHref' => $all];
        }

        return $groups;
    }
}
