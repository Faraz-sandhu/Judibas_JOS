<?php

namespace App\Modules\Accounting\Http\Middleware;

use App\Modules\Accounting\Services\ImprestFunds;
use Closure;
use Illuminate\Http\Request;

class RestrictImprestOfficer
{
    public function handle(Request $r, Closure $next)
    {
        if (! in_array($r->method(), ['GET', 'HEAD']) && ImprestFunds::restricted($r)) {
            $allowed = $r->is('accounting/api/imprest/spend', 'accounting/api/imprest/*/cancel', 'accounting/api/approvals/submit', 'accounting/api/approvals/*/signed-copy', 'accounting/api/approvals/*/actions', 'accounting/api/notifications/*');
            abort_unless($allowed, 403, 'Your company access is restricted to your assigned Imprest / Petty Cash fund.');
        }

        return $next($r);
    }
}
