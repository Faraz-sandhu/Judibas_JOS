<?php

namespace App\Modules\Pms\Services;

use App\Services\PlatformAccess;
use Illuminate\Support\Facades\DB;

/** A per-request, per-browser-tab company selection; never stored in shared session state. */
class PmsCompanyContext
{
    public static function catalogueAll(): bool
    {
        return request()->query('workspace_company',request()->header('X-PMS-Company'))==='all';
    }

    public static function platformIds(): ?array
    {
        $r = request();
        if (! $r->hasSession() || (! $r->user() && ! $r->session()->get('judibas_admin'))) {
            return null;
        }
        $catalogue=self::catalogueAll()?\App\Services\CatalogueCompanyScope::ids('projects',$r):null;
        if (PlatformAccess::administrator($r)) {
            return $catalogue;
        }
        if ($r->attributes->has('pms.allowed_platform_ids')) {
            return $r->attributes->get('pms.allowed_platform_ids');
        }
        $ids = \App\Services\ProductCompanyAccess::ids($r->user()->id,'projects');
        if($catalogue!==null)$ids=array_values(array_intersect($ids,$catalogue));
        $r->attributes->set('pms.allowed_platform_ids', $ids);

        return $ids;
    }

    public static function selected(): ?object
    {
        $r = request();
        if ($r->attributes->has('pms.company_context')) {
            return $r->attributes->get('pms.company_context');
        }
        $value = $r->query('workspace_company', $r->header('X-PMS-Company'));
        $r->attributes->set('pms.company_context', null);
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }
        abort_unless(is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0, 422, 'Choose a valid PMS company.');
        PmsAccess::authorize($r);
        $allowed = self::platformIds();
        abort_if($allowed !== null && ! in_array((int) $value, $allowed, true), 403, 'This company is not assigned to your PMS access.');
        $company = DB::table('communication_companies')->where('id', (int) $value)->first(['id', 'name']);
        abort_unless($company, 404, 'PMS company not found.');
        $r->attributes->set('pms.company_context', $company);

        return $company;
    }

    public static function restrict(): bool
    {
        return self::selected() !== null || self::platformIds() !== null;
    }

    public static function users($query, string $column = 'users.id'): void
    {
        $selected = self::selected();
        $ids = $selected ? [$selected->id] : self::platformIds();
        if ($ids === null) {
            return;
        }
        \App\Services\ProductCompanyAccess::users($query,'projects',$ids,$column);
    }
}
