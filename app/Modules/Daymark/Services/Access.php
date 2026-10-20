<?php

namespace App\Modules\Daymark\Services;

use App\Modules\Daymark\Models\User;
use App\Services\CommunicationAccess;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Access
{
    public static function actor(Request $r): User
    {
        abort_unless(PlatformAccess::product($r, 'daymark'), 403, 'Daymark access is required.');
        if (PlatformAccess::administrator($r)) {
            $u = new User;
            $u->forceFill(['id' => $r->user()?->id ?? 0, 'role' => 'super_admin', 'active' => true, 'name' => $r->user()?->name ?? 'Super Admin']);

            return $u;
        }
        $u = User::find($r->user()->id);
        abort_unless($u && $u->active, 403, 'Ask your administrator to configure your Daymark reporting profile.');
        abort_unless(in_array($u->company_id, CommunicationAccess::companies($u->id), true), 403, 'Your Daymark company is no longer assigned.');

        return $u;
    }

    public static function permissions(Request $r): array
    {
        return RoleAccess::permissions(self::actor($r));
    }

    public static function allows(Request $r, string $ability): bool
    {
        return in_array($ability, self::permissions($r), true);
    }

    public static function require(Request $r, string $ability): void
    {
        abort_unless(self::allows($r, $ability), 403, 'You do not have permission for this Daymark action.');
    }

    public static function audit(Request $r, string $action, string $target): void
    {
        DB::table('daymark_audits')->insert(['actor_id' => $r->user()?->id, 'name' => $r->user()?->name ?? 'Super Admin', 'action' => $action, 'target' => $target, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function validateSelection(array $v): void
    {
        if (! in_array('daymark', $v['product_slugs'] ?? [], true)) {
            return;
        }
        $company = $v['daymark_company_id'] ?? ($v['company_ids'][0] ?? null);
        abort_unless($company && in_array((int) $company, array_map('intval', $v['company_ids'] ?? []), true), 422, 'Select an assigned company for Daymark.');
        if (! empty($v['daymark_department_id'])) {
            abort_unless(DB::table('daymark_departments')->where('company_id', $company)->where('id', $v['daymark_department_id'])->exists(), 422, 'Select a Daymark department in the reporting company.');
        }
        if (! empty($v['daymark_custom_role_id'])) {
            abort_unless(DB::table('daymark_custom_roles')->where('company_id', $company)->where('id', $v['daymark_custom_role_id'])->exists(), 422, 'Select a Daymark role in the reporting company.');
        }
        foreach ($v['daymark_manager_ids'] ?? [] as $id) {
            $manager = User::where('company_id', $company)->whereIn('role', ['pm', 'om'])->find($id);
            abort_unless($manager && $manager->active, 422, 'Choose active Daymark managers in the same company.');
        }
    }

    public static function sync(\App\Models\User $account, array $v): void
    {
        if (! in_array('daymark', $v['product_slugs'] ?? [], true)) {
            return;
        }
        $existing = User::find($account->id);
        $company = (int) ($v['daymark_company_id'] ?? $existing?->company_id ?? $v['company_ids'][0]);
        abort_unless(in_array($company, array_map('intval', $v['company_ids']), true), 422, 'Keep the reporting company selected, or change the Daymark reporting company.');
        abort_if($existing && $existing->company_id !== $company && $existing->reports()->exists(), 422, 'This person has Daymark report history. Keep their reporting company to preserve company isolation.');
        $role = $v['daymark_role'] ?? $existing?->role ?? 'employee';
        $custom = array_key_exists('daymark_custom_role_id', $v) ? $v['daymark_custom_role_id'] : $existing?->custom_role_id;
        if ($custom) {
            $role = DB::table('daymark_custom_roles')->where('id', $custom)->where('company_id', $company)->value('base_role');
        }
        $department = ! empty($v['daymark_department_id']) ? DB::table('daymark_departments')->where('id', $v['daymark_department_id'])->where('company_id', $company)->value('name') : ($existing?->department ?? '');
        DB::table('daymark_members')->updateOrInsert(['id' => $account->id], ['company_id' => $company, 'role' => $role, 'department' => $department, 'designation' => $v['daymark_designation'] ?? $existing?->designation ?? '', 'custom_role_id' => array_key_exists('daymark_custom_role_id', $v) ? $custom : $existing?->custom_role_id, 'active' => $v['daymark_active'] ?? $existing?->getRawOriginal('active') ?? true, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now()]);
        if (array_key_exists('daymark_manager_ids', $v)) {
            $ids = $v['daymark_manager_ids'];
            abort_if(in_array($account->id, $ids), 422, 'A person cannot report to themselves.');
            User::findOrFail($account->id)->managers()->sync($ids);
        }
        if ($existing && ($existing->company_id !== $company || ! in_array($role,['pm', 'om'],true))) {
            $existing->employees()->detach();
        }
    }
}
