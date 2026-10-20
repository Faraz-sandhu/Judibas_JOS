<?php

namespace App\Modules\Daymark\Http\Controllers;

use App\Models\MailSetting;
use App\Modules\Daymark\Models\Company;
use App\Modules\Daymark\Models\Department;
use App\Modules\Daymark\Models\User;
use App\Modules\Daymark\Services\Access;
use App\Modules\Daymark\Services\DepartmentDirectory;
use App\Modules\Daymark\Services\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManagementController
{
    public function password(Request $r)
    {
        Access::actor($r);
        abort_unless($r->user(), 403);
        $v = $r->validate(['current' => 'required|current_password', 'password' => 'required|string|min:12|max:200|confirmed']);
        $r->user()->forceFill(['password' => $v['password'], 'remember_token' => Str::random(60)])->save();
        Access::audit($r, 'Password changed', 'Shared Judibas account');

        return ['message' => 'Shared account password changed.'];
    }

    private function company(Request $r, int $id): void
    {
        $u = Access::actor($r);
        Company::findOrFail($id);
        abort_unless($u->role === 'super_admin' || $u->company_id === $id, 403);
    }

    public function department(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'name' => 'required|string|max:200']);
        $this->company($r, (int) $v['company_id']);
        $department = DB::transaction(function () use ($r, $v) {
            $name = DepartmentDirectory::resolve((int) $v['company_id'], ['department' => $v['name']]);
            Access::audit($r, 'Department saved', $name);

            return Department::where('company_id', $v['company_id'])->where('name', $name)->firstOrFail();
        });

        return ['message' => 'Department saved.', 'department' => $department];
    }

    public function deleteDepartment(Request $r)
    {
        $v = $r->validate(['id' => 'required|integer', 'replacement_id' => 'nullable|integer']);
        $d = Department::findOrFail($v['id']);
        $this->company($r, $d->company_id);
        DB::transaction(function () use ($d, $v, $r) {
            Company::whereKey($d->company_id)->lockForUpdate()->firstOrFail();
            $d = Department::whereKey($d->id)->lockForUpdate()->firstOrFail();
            $users = User::where('company_id', $d->company_id)->where('department', $d->name);
            $invites = DB::table('platform_invitations')->whereNull('accepted_at')->whereNull('revoked_at')->get()->filter(fn ($i) => ($a = json_decode($i->access, true)) && ($a['daymark_department_id'] ?? null) == $d->id);
            $replacement = ! empty($v['replacement_id']) ? Department::where('company_id', $d->company_id)->where('id', '!=', $d->id)->find($v['replacement_id']) : null;
            abort_if(! empty($v['replacement_id']) && ! $replacement, 422, 'Choose a replacement in the same company.');
            abort_if(($users->exists() || $invites->isNotEmpty()) && ! $replacement, 422, 'Select a replacement department for linked people and invitations.');
            if ($replacement) {
                $users->update(['department' => $replacement->name]);
                foreach ($invites as $i) {
                    $a = json_decode($i->access, true);
                    $a['daymark_department_id'] = $replacement->id;
                    DB::table('platform_invitations')->where('id', $i->id)->update(['access' => json_encode($a)]);
                }
            }$d->delete();
            Access::audit($r, 'Department deleted', $d->name);
        });

        return ['message' => 'Department deleted.'];
    }

    public function user(Request $r)
    {
        $v = $r->validate(['id' => 'required|integer', 'role' => ['required', Rule::in(array_diff(RoleAccess::ROLES, ['super_admin']))], 'custom_role_id' => 'nullable|integer', 'active' => 'required|boolean', 'department_id' => 'nullable|integer', 'department' => 'required|string|max:200', 'designation' => 'required|string|max:200', 'manager_ids' => 'present|array', 'manager_ids.*' => 'integer|distinct']);
        $u = Access::actor($r);
        $target = User::findOrFail($v['id']);
        $this->company($r, $target->company_id);
        abort_if($u->id === $target->id || $target->account?->judibas_administrator, 403, 'This account cannot be changed here.');
        $v = CustomRoleController::assignment($v, $target->company_id);
        $selection = ['product_slugs' => ['daymark'], 'company_ids' => [$target->company_id], 'daymark_company_id' => $target->company_id, 'daymark_manager_ids' => $v['manager_ids'], 'daymark_custom_role_id' => $v['custom_role_id'] ?? null, 'daymark_department_id' => $v['department_id'] ?? null];
        Access::validateSelection($selection);
        abort_if(in_array($target->id, $v['manager_ids']), 422, 'A person cannot report to themselves.');
        DB::transaction(function () use ($target, $v, $r) {
            $department = DepartmentDirectory::resolve($target->company_id, $v, Access::allows($r, 'manage_departments'));
            $target->update(['role' => $v['role'], 'custom_role_id' => $v['custom_role_id'] ?? null, 'active' => $v['active'], 'department' => $department, 'designation' => $v['designation']]);
            $target->managers()->sync($v['manager_ids']);
            if (! in_array($v['role'], ['pm', 'om'])) {
                $target->employees()->detach();
            }Access::audit($r, 'Daymark access updated', $target->name);
        });

        return ['message' => 'Daymark access saved. Other product access is unchanged.'];
    }

    public function invitations(Request $r)
    {
        $u = Access::actor($r);

        return ['invitations' => DB::table('platform_invitations')->latest('id')->get()->map(function ($i) {
            $i->access = json_decode($i->access, true);

            return $i;
        })->filter(fn ($i) => in_array('daymark', $i->access['product_slugs'] ?? []) && ($u->role === 'super_admin' || ($i->access['daymark_company_id'] ?? null) == $u->company_id))->map(fn ($i) => collect((array) $i)->except('token_hash')->all())->values()];
    }

    public function invite(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'name' => 'required|string|max:100', 'email' => 'required|email|max:200', 'role' => ['required', Rule::in(array_diff(RoleAccess::ROLES, ['super_admin']))], 'department_id' => 'required|integer', 'designation' => 'required|string|max:200', 'custom_role_id' => 'nullable|integer', 'manager_ids' => 'sometimes|array', 'manager_ids.*' => 'integer|distinct', 'delivery_method' => 'required|in:manual,email']);
        $this->company($r, (int) $v['company_id']);
        $v['email'] = strtolower($v['email']);
        abort_if($v['email'] === strtolower(config('judibas.admin_email')), 422, 'This address is reserved for Super Admin.');
        $access = ['product_slugs' => ['daymark'], 'company_ids' => [(int) $v['company_id']], 'daymark_company_id' => (int) $v['company_id'], 'daymark_role' => $v['role'], 'daymark_department_id' => $v['department_id'], 'daymark_designation' => $v['designation'], 'daymark_custom_role_id' => $v['custom_role_id'] ?? null, 'daymark_manager_ids' => $v['manager_ids'] ?? []];
        Access::validateSelection($access);
        $existing = User::whereHas('account', fn ($q) => $q->where('email', $v['email']))->first();
        abort_if($existing && $existing->company_id != $v['company_id'], 422, 'This account already reports in another company.');
        $token = Str::random(64);
        $id = DB::transaction(function () use ($v, $access, $token) {
            $pending = DB::table('platform_invitations')->where('email', $v['email'])->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
            abort_if($pending, 422, 'An invitation is already pending for this email. Revoke it in Invitations before replacing it.');

            return DB::table('platform_invitations')->insertGetId(['email' => $v['email'], 'name' => $v['name'], 'access' => json_encode($access), 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'delivery_status' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        });
        $url = $r->getSchemeAndHttpHost().'/invitations/'.$token;
        $sent = false;
        if ($v['delivery_method'] === 'email' && MailSetting::current()->invitation_emails_enabled && ! in_array(config('mail.default'), ['log', 'array'])) {
            try {
                Mail::raw('You are invited to Daymark in Judibas. Accept your invitation: '.$url.' (valid for seven days).', fn ($m) => $m->to($v['email'])->subject('Your Judibas Daymark invitation'));
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }DB::table('platform_invitations')->where('id', $id)->update(['delivery_status' => $sent ? 'sent' : 'manual']);
        Access::audit($r, 'Invitation created', $v['email']);

        return ['message' => $sent ? 'Invitation sent.' : 'Invitation created. Share the secure link.', 'invitation_url' => $url];
    }

    public function revoke(Request $r)
    {
        $v = $r->validate(['id' => 'required|integer']);
        $i = DB::table('platform_invitations')->find($v['id']);
        abort_unless($i, 404);
        $a = json_decode($i->access, true);
        abort_unless(in_array('daymark', $a['product_slugs'] ?? [], true), 403);
        $this->company($r, (int) $a['daymark_company_id']);
        abort_unless($a['product_slugs'] === ['daymark'] || Access::actor($r)->role === 'super_admin', 403, 'Manage multi-product invitations from Administration.');
        abort_if($i->accepted_at || $i->revoked_at,409,'Invitation already used or revoked.');
        DB::table('platform_invitations')->where('id',$i->id)->update(['revoked_at' => now()]);
        Access::audit($r,'Invitation revoked',$i->email);

        return ['message' => 'Invitation revoked.'];
    }
}
