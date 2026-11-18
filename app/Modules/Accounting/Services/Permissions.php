<?php

namespace App\Modules\Accounting\Services;

use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Permissions
{
    public static function catalog(): array
    {
        $groups = [
            'imprest' => ['Imprest / Petty Cash', ['view','create','manage','post']],
            'overview' => ['Overview', ['view']], 'chart' => ['Chart of accounts', ['view','initialize','create','update','delete']],
            'customers' => ['Customers', ['view','create','update']], 'suppliers' => ['Suppliers', ['view','create','update']],
            'sales' => ['Sales invoices', ['view','create','update','post','reverse','print']],
            'purchases' => ['Purchase invoices', ['view','create','update','post','reverse','print']],
            'purchase_orders' => ['Purchase orders', ['view','create','confirm','cancel','convert']],
            'sales_orders' => ['Sales-side orders', ['view','create','confirm','cancel','convert']],
            'payments' => ['Invoice payments', ['view','create','post','reverse']],
            'journals' => ['Journal entries', ['view','create','update','post','reverse','print']],
            'cash_receipts' => ['Cash receipt vouchers', ['view','create','update','post','reverse','print']],
            'cash_payments' => ['Cash payment vouchers', ['view','create','update','post','reverse','print']],
            'bank_payments' => ['Bank payment vouchers', ['view','create','update','post','reverse','print']],
            'bank_receipts' => ['Bank receipt vouchers', ['view','create','update','post','reverse','print']],
            'expense_vouchers' => ['Expense vouchers', ['view','create','update','post','reverse','print']],
            'income_vouchers' => ['Income vouchers', ['view','create','update','post','reverse','print']],
            'reports' => ['Reports', ['ledger','trial','profit','balance','receivables','payables','export']],
            'cash_reviews' => ['Cash-basis allocations', ['view','resolve']], 'books' => ['Company books', ['view','manage']],
            'notifications'=>['Activity notifications',['view']],
            'audit' => ['Audit history', ['view']], 'roles' => ['Roles and permissions', ['view','manage']],
            'workflow' => ['Approval workflow', ['view','submit','upload_signed','finance_review','ceo_approve','finance_verify','finalize','configure']],
        ];
        return collect($groups)->map(fn ($spec, $key) => ['key' => $key, 'name' => $spec[0], 'permissions' => array_map(fn ($action) => ['key' => $key.'.'.$action, 'name' => ucwords(str_replace('_', ' ', $action))], $spec[1])])->values()->all();
    }

    public static function keys(): array { return collect(self::catalog())->pluck('permissions')->flatten(1)->pluck('key')->all(); }

    public static function presets(): array
    {
        $all = self::keys();
        $views = array_values(array_filter($all, fn ($p) => (str_ends_with($p, '.view') || str_ends_with($p, '.print') || str_starts_with($p, 'reports.')) && ! in_array($p, ['roles.view','books.view','audit.view','notifications.view','reports.export'])));
        $finance = array_values(array_unique(array_merge($views, ['imprest.create','reports.export','workflow.submit','workflow.upload_signed','workflow.finance_review','workflow.finance_verify'], array_filter($all, fn ($p) => preg_match('/^(chart|customers|suppliers|sales|purchases|journals|cash_receipts|cash_payments|bank_receipts|bank_payments|expense_vouchers|income_vouchers|purchase_orders|sales_orders)\.(create|update|initialize|print)$/', $p) || $p === 'payments.create'))));
        return [
            'viewer' => ['Viewer', $views],
            // Preserve existing Accountant capabilities while enabling narrower custom roles.
            'accountant' => ['Accountant', array_values(array_filter($all, fn ($p) => ! in_array($p, ['imprest.manage','imprest.post','notifications.view','chart.delete','sales.reverse','purchases.reverse','payments.reverse','journals.reverse','cash_receipts.reverse','cash_payments.reverse','bank_payments.reverse','bank_receipts.reverse','expense_vouchers.reverse','income_vouchers.reverse','books.manage','roles.manage','workflow.configure','workflow.ceo_approve','workflow.finalize'])))],
            'manager' => ['Accounting Manager', $all],
            'finance_officer' => ['Finance Officer', $finance],
            'senior_finance_manager' => ['Senior Finance Manager', array_values(array_diff($all, ['roles.manage','workflow.configure','workflow.ceo_approve']))],
            'junior_finance_officer' => ['Junior Finance Officer', ['imprest.view','imprest.create','cash_payments.view','cash_payments.print','journals.view','journals.print','workflow.view','workflow.submit','workflow.upload_signed']],
            'ceo' => ['Company CEO', ['workflow.view','workflow.ceo_approve','reports.profit','reports.balance','reports.receivables','reports.payables']],
        ];
    }

    public static function effective(Request $r): array
    {
        if (! PlatformAccess::product($r, 'accounts')) return [];
        if (PlatformAccess::administrator($r)) return self::keys();
        $role = DB::table('accounting_members')->where('user_id', $r->user()->id)->value('role') ?? 'viewer';
        $json = DB::table('accounting_roles')->where('slug', $role)->where('active', true)->value('permissions');

        $keys = array_values(array_intersect(json_decode($json ?? '[]', true), self::keys()));
        if (ImprestFunds::restricted($r)) $keys = array_values(array_intersect($keys, self::presets()['junior_finance_officer'][1]));
        return $keys;
    }

    public static function can(Request $r, string $permission): bool { return in_array($permission, self::effective($r), true); }
    public static function require(Request $r, string $permission): void { abort_unless(self::can($r, $permission), 403, 'Your Accounting role does not allow this action ('.$permission.').'); }
    public static function journalGroup(string $kind): string { return VoucherTypes::TYPES[$kind][2] ?? 'journals'; }
}
