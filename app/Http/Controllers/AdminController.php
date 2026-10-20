<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PortalContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private function authorizeAdmin(Request $r): void
    {
        \App\Services\PlatformAccess::requireAdmin($r);
    }

    public function data(Request $r)
    {
        $this->authorizeAdmin($r);
        $pmsUsers=\App\Modules\Pms\Models\User::withoutGlobalScopes()->with(['roles:id','departments:id'])->get()->keyBy('id');
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'is_active', 'judibas_administrator', 'company_id', 'communication_admin'])->map(function ($u) use ($pmsUsers) {
            $u->canteen_level_ids=DB::table('canteen_profiles')->where('user_id',$u->id)->pluck('level_id','company_id');
            $dm=\App\Modules\Daymark\Models\User::find($u->id);$u->daymark_company_id=$dm?->company_id;$u->daymark_active=$dm?(bool)$dm->getRawOriginal('active'):true;$u->daymark_role=$dm?->role??'employee';$u->daymark_department_id=$dm?\Illuminate\Support\Facades\DB::table('daymark_departments')->where('company_id',$dm->company_id)->where('name',$dm->department)->value('id'):null;$u->daymark_custom_role_id=$dm?->custom_role_id;$u->daymark_designation=$dm?->designation??'';$u->daymark_manager_ids=$dm?->managers()->pluck('daymark_members.id')??[];
            $u->canteen_role=DB::table('canteen_profiles')->where('user_id',$u->id)->value('role')??'employee';
            $u->company_ids = \App\Services\CommunicationAccess::companies($u->id);
            $pms=$pmsUsers[$u->id];
            $u->pms_role_ids=$pms->roles->pluck('id');
            $u->pms_department_ids=$pms->departments->pluck('id');
            $u->designation=$pms->designation; $u->surname=$pms->surname;
            $u->product_slugs = DB::table('user_product_access')->where('user_id', $u->id)->pluck('product_slug');

            return $u;
        });

        return response()->json(['blogs' => DB::table('blog_posts')->orderByDesc('id')->get(), 'pages' => PortalContent::pages(), 'products' => PortalContent::products(true), 'users' => $users, 'companies' => DB::table('communication_companies')->orderBy('name')->get(), 'daymark_role_matrix'=>\App\Modules\Daymark\Services\RoleAccess::matrix(),'daymark_departments'=>DB::table('daymark_departments')->orderBy('name')->get(),'daymark_roles'=>DB::table('daymark_custom_roles')->orderBy('name')->get(),'daymark_managers'=>\App\Modules\Daymark\Models\User::whereIn('role',['pm','om'])->get()->filter(fn($m)=>$m->active)->values(), 'canteen_levels'=>DB::table('canteen_levels')->orderBy('name')->get(['id','name','company_id','active']), 'pms_roles'=>\App\Modules\Pms\Models\Role::orderBy('role_name')->get(['id','role_name','role_key']), 'pms_departments'=>\App\Modules\Pms\Models\Department::orderBy('dept_name')->get(['id','dept_name','status']), 'pms_tasks'=>DB::table('pms_tasks')->leftJoin('pms_projects','pms_projects.id','=','pms_tasks.project_id')->leftJoin('pms_companies','pms_companies.id','=','pms_projects.company_id')->orderByDesc('pms_tasks.id')->limit(200)->get(['pms_tasks.id','pms_tasks.title','pms_tasks.project_id','pms_companies.platform_company_id']), 'groups'=>DB::table('communication_conversations')->where('kind','group')->get(['id','name','company_id']), 'announcement' => PortalContent::announcement()]);
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        $this->authorizeAdmin($r);
        $slugs = array_column(config('judibas.products'), 'slug');
        $now = now();
        if ($resource === 'blogs') {
            $v = $r->validate(['title' => 'required|string|max:200', 'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($id)], 'excerpt' => 'nullable|string|max:1000', 'body' => 'required|string|max:100000', 'published' => 'required|boolean']);
            $this->persist('blog_posts', $v, $id);
        } elseif ($resource === 'pages') {
            $v = $r->validate(['slug' => ['required', Rule::in(array_keys(config('pages')))], 'title' => 'required|string|max:200', 'intro' => 'nullable|string|max:2000', 'body' => 'required|string|max:100000']);
            DB::table('site_pages')->updateOrInsert(['slug' => $v['slug']], $v + ['updated_at' => $now, 'created_at' => $now]);
        } elseif ($resource === 'products') {
            $v = $r->validate(['slug' => ['required', Rule::in($slugs)], 'name' => 'required|string|max:100', 'description' => 'required|string|max:1000', 'icon' => 'nullable|string|max:16', 'color' => ['required', 'regex:/^#[a-fA-F0-9]{6}$/'], 'display_order' => 'required|integer|min:0|max:10000', 'visible' => 'required|boolean']);
            DB::table('product_settings')->updateOrInsert(['slug' => $v['slug']], $v + ['updated_at' => $now, 'created_at' => $now]);
        } elseif ($resource === 'users') {
            \App\Services\PlatformAccess::protectAccount($r,$id);
            if($r->has('judibas_administrator')&&!\App\Services\PlatformAccess::super($r))abort(403,'Only Super Admin can assign administrator privileges.');
            $email = $r->input('email');
            if (is_string($email)) {
                $r->merge(['email' => strtolower($email)]);
            }
            $v = $r->validate(array_filter(\App\Services\UserProductAccess::rules(),fn($key)=>str_starts_with($key,'daymark_'),ARRAY_FILTER_USE_KEY)+['canteen_level_ids'=>'sometimes|array|max:100','canteen_level_ids.*'=>'nullable|integer|exists:canteen_levels,id','canteen_role'=>'sometimes|in:employee,admin,kitchen,cashier','judibas_administrator'=>'sometimes|boolean','pms_role_ids'=>'sometimes|array','pms_role_ids.*'=>'integer|distinct|exists:pms_roles,id','pms_department_ids'=>'sometimes|array','pms_department_ids.*'=>'integer|distinct|exists:pms_departments,id','surname'=>'sometimes|nullable|string|max:255','designation'=>'sometimes|nullable|string|max:255','company_ids'=>'sometimes|array|max:100','company_ids.*'=>'required|integer|distinct|exists:communication_companies,id','company_id' => [Rule::requiredIf(fn () => in_array('communication', $r->input('product_slugs', [])) && !$r->input('company_ids')), 'nullable', 'integer', 'exists:communication_companies,id'], 'communication_admin' => 'sometimes|boolean', 'name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($id)], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:200'], 'is_active' => 'required|boolean', 'product_slugs' => 'present|array', 'product_slugs.*' => ['string', Rule::in($slugs)]]);
            abort_if(strtolower($v['email']) === strtolower((string) config('judibas.admin_email')), 422, 'This email is reserved for Super Admin.');
            abort_if(! empty($v['communication_admin']) && ! in_array('communication', $v['product_slugs']), 422, 'A Communication Admin needs Communication product access.');
            $v['company_ids']=$v['company_ids']??($id&&!array_key_exists('company_id',$v)?\App\Services\CommunicationAccess::companies($id):array_values(array_filter([$v['company_id']??null])));
            if(empty($v['judibas_administrator'])&&in_array('projects',$v['product_slugs'])&&(!$id||!DB::table('user_product_access')->where('user_id',$id)->where('product_slug','projects')->exists())&&!count($v['pms_role_ids']??[])) throw \Illuminate\Validation\ValidationException::withMessages(['pms_role_ids'=>'Select a PMS role when granting PMS access.']);
            if(!empty($v['judibas_administrator'])&&empty($v['pms_role_ids']))unset($v['pms_role_ids']);
            \App\Services\UserProductAccess::validateSelection($v);
            DB::transaction(function () use ($v, $id) {
                $u=$id?User::findOrFail($id):new User;
                if(array_key_exists('judibas_administrator',$v))$u->judibas_administrator=$v['judibas_administrator'];
                $u->name=$v['name'];$u->email=$v['email'];$u->is_active=$v['is_active'];$u->status=$v['is_active']?1:2;
                foreach(['surname','designation'] as $key) if(array_key_exists($key,$v))$u->$key=$v[$key];
                if(!empty($v['password']))$u->password=$v['password'];$u->save();
                \App\Services\UserProductAccess::sync($u,$v);
            });
        } elseif ($resource === 'announcement') {
            $v = $r->validate(['title' => 'required|string|max:100', 'text' => 'required|string|max:250', 'url' => ['required', 'string', 'max:200', 'regex:/^\\/(?!\\/)[a-zA-Z0-9\\/#?=_-]*$/']]);
            DB::table('site_content')->updateOrInsert(['key' => 'announcement'], ['value' => json_encode($v), 'updated_at' => $now, 'created_at' => $now]);
        } else {
            abort(404);
        }

        return response()->json(['message' => 'Changes saved.']);
    }

    private function persist(string $table, array $values, ?int $id): void
    {
        if ($id) {
            abort_unless(DB::table($table)->where('id', $id)->exists(), 404);
            DB::table($table)->where('id', $id)->update($values + ['updated_at' => now()]);
        } else {
            DB::table($table)->insert($values + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function delete(Request $r, string $resource, int $id)
    {
        $this->authorizeAdmin($r);
        if($resource==='users' && DB::table('daymark_reports')->where('user_id',$id)->exists())abort(422,'This employee has Daymark report history. Deactivate the account or remove product access to preserve their reports.');
        if($resource==='users')\App\Services\PlatformAccess::protectAccount($r,$id);
        $table = match ($resource) {
            'blogs' => 'blog_posts','users' => 'users',default => null
        };
        abort_unless($table, 404);
        abort_unless(DB::table($table)->where('id', $id)->delete(), 404);

        return response()->json(['message' => 'Deleted.']);
    }
}
