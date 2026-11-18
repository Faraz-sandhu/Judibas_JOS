<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCatalogue
{
    public const THEMES = ['workspace', 'directory', 'classic', 'disclosure'];

    /** Grouped products use the same company lists as the product page. */
    public static function invitationCompanyScopes(): array
    {
        $companies = DB::table('communication_companies')->get(['id', 'name']);
        $scopes = [];
        foreach (collect(config('product-catalogue.groups', []))->groupBy('slug') as $slug => $groups) {
            $names = $groups->flatMap(fn ($group) => $group['companies'])->map(fn ($name) => mb_strtolower($name))->all();
            $scopes[$slug] = $companies->filter(fn ($company) => in_array(mb_strtolower($company->name), $names, true))
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        return $scopes;
    }

    public static function validateInvitationCompanies(array $access): void
    {
        $scopes = self::invitationCompanyScopes();
        if (array_key_exists('product_company_ids', $access)) {
            foreach ($access['product_company_ids'] as $slug => $companies) {
                if (isset($scopes[$slug]) && array_diff($companies, $scopes[$slug])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['product_company_ids.'.$slug => 'Choose companies from this product’s directory.']);
                }
            }
            return;
        }
        $allowed = [];
        foreach ($access['product_slugs'] as $slug) {
            // Universal products have no company-specific catalogue group.
            if (! array_key_exists($slug, $scopes)) {
                return;
            }
            $allowed = array_merge($allowed, $scopes[$slug]);
        }
        if (array_diff($access['company_ids'], $allowed)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_ids' => 'Choose companies belonging to the selected products on the Products page.',
            ]);
        }
    }

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
            $companies->whereIn('id', $request->user()?->is_active ? ProductCompanyAccess::allIds($request->user()->id) : []);
        }
        $companies = $companies->get(['id', 'name'])->keyBy(fn ($c) => strtolower($c->name));
        $allowed = collect($products)->keyBy('slug');
        $groups = [];
        foreach (config('product-catalogue.groups') as $definition) {
            if (! $allowed->has($definition['slug']) && ! ($admin && $definition['planned'])) {
                continue;
            }
            $productCompanies = $admin ? null : ProductCompanyAccess::ids($request->user()->id,$definition['slug']);
            $external = collect(config('judibas.products'))->firstWhere('slug',$definition['slug'])['external_url'] ?? null;
            $items = [];
            foreach ($definition['companies'] as $name) {
                $company = $companies->get(strtolower($name));
                if (! $company || ($productCompanies !== null && ! in_array((int)$company->id,$productCompanies,true))) {
                    continue;
                }
                $href = $external;
                if (! $definition['planned'] && $definition['slug'] === 'accounts') {
                    $href = CompanyWorkspace::url('accounting',$company->id).'?workspace_group='.$definition['id'];
                }
                if (! $definition['planned'] && $definition['slug'] === 'projects') {
                    $href = CompanyWorkspace::url('pms',$company->id);
                }
                $items[] = ['name' => $company->name, 'href' => $href];
            }
            $all = $external ?: (! $definition['planned'] ? ($definition['slug'] === 'accounts' ? ($admin ? '/accounting/all?workspace_group='.$definition['id'] : '/accounting?workspace_group='.$definition['id']) : '/pms/all') : null);
            if (! $admin && ! count($items)) {
                continue;
            }
            $groups[] = ['id' => $definition['id'], 'name' => $definition['name'], 'slug' => $definition['slug'], 'description' => $definition['description'], 'planned' => $definition['planned'] && !$external, 'externalUrl' => $external, 'companies' => $items, 'allHref' => $all];
        }

        return $groups;
    }
}
