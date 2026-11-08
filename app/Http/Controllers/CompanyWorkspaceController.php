<?php

namespace App\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess;
use App\Modules\Canteen\Http\Controllers\CatalogController;
use App\Modules\Canteen\Services\CanteenAccess;
use App\Modules\Daymark\Services\Access;
use App\Modules\Pms\Http\Controllers\WorkspaceController;
use App\Services\CommunicationAccess;
use App\Services\CompanyWorkspace;
use App\Services\CvBankAccess;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;

class CompanyWorkspaceController extends Controller
{
    public function show(Request $r, string $workspace, string $companySlug)
    {
        if (! $r->user() && ! PlatformAccess::super($r)) {
            $r->session()->put('url.intended', $r->getRequestUri());

            return redirect('/login');
        }
        $product = CompanyWorkspace::PRODUCTS[$workspace] ?? null;
        abort_unless($product && PlatformAccess::product($r, $product), 403, 'Product access is required.');
        $company = null;
        if ($companySlug === 'all') {
            abort_unless(in_array($workspace, ['pms', 'accounting', 'daymark'], true), 404);
            if ($workspace === 'accounting') {
                abort_unless(PlatformAccess::administrator($r), 403);
            }
        } else {
            $company = collect(CompanyWorkspace::directory())->firstWhere('slug', $companySlug);
            abort_unless($company, 404, 'Company workspace not found.');
            abort_unless(PlatformAccess::administrator($r) || in_array($company['id'], CommunicationAccess::companies($r->user()->id), true), 403, 'This company is not assigned to you.');
        }
        $id = $company['id'] ?? 'all';
        $r->attributes->set('workspace.root', '/'.$workspace);
        $r->attributes->set('workspace.company', ['product' => $workspace, 'id' => $id, 'slug' => $companySlug]);
        $r->query->set($workspace === 'pms' ? 'workspace_company' : 'company_id', (string) $id);
        if ($workspace === 'pms') {
            return app(WorkspaceController::class)->page($r);
        }
        if ($workspace === 'accounting' && $company) {
            AccountingAccess::company($r, $company['id']);
        }
        if ($workspace === 'canteen') {
            CanteenAccess::company($r, $company['id']);

            return app(CatalogController::class)->page($r);
        }
        if ($workspace === 'cv-bank') {
            CvBankAccess::company($r, $company['id']);

            return app(CvBankController::class)->dashboard($r);
        }
        if ($workspace === 'daymark') {
            Access::actor($r);
        }

        return app(PortalController::class)->index($r,$product);
    }
}
