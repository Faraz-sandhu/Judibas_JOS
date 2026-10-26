<?php

namespace App\Modules\Accounting\Services;

use App\Services\CommunicationAccess;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingAccess
{
    public static function role(Request $r): string
    {
        abort_unless(PlatformAccess::product($r, 'accounts'), 403, 'Accounting access is required.');

        return PlatformAccess::administrator($r) ? 'manager' : (DB::table('accounting_members')->where('user_id', $r->user()->id)->value('role') ?? 'viewer');
    }

    public static function companies(Request $r)
    {
        self::role($r);
        $q = DB::table('communication_companies')->orderBy('name');
        if (! PlatformAccess::administrator($r)) {
            $q->whereIn('id', CommunicationAccess::companies($r->user()->id));
        }

        return $q->get(['id', 'name']);
    }

    public static function company(Request $r, int $company, string $ability = 'view'): void
    {
        $role = self::role($r);
        abort_unless(self::companies($r)->contains('id', $company), 403, 'This company is not assigned to you.');
        abort_unless($ability === 'view' || in_array($role, ['accountant', 'manager'], true) && $ability === 'manage' || $role === 'manager', 403, 'Your Accounting role does not allow this action.');
    }

    public static function audit(Request $r, int $company, string $action, string $subject, array $details = []): void
    {
        DB::table('accounting_audits')->insert(['company_id' => $company, 'actor_id' => $r->user()?->id, 'actor_name' => PlatformAccess::super($r) ? 'Super Admin' : $r->user()->name, 'action' => $action, 'subject' => $subject, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }
}
