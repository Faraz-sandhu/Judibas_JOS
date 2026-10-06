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
        abort_unless($r->session()->get('judibas_admin'), 403);
    }

    public function data(Request $r)
    {
        $this->authorizeAdmin($r);
        $users = User::orderBy('name')->get(['id', 'name', 'email', 'is_active', 'company_id', 'communication_admin'])->map(function ($u) {
            $u->product_slugs = DB::table('user_product_access')->where('user_id', $u->id)->pluck('product_slug');

            return $u;
        });

        return response()->json(['blogs' => DB::table('blog_posts')->orderByDesc('id')->get(), 'pages' => PortalContent::pages(), 'products' => PortalContent::products(true), 'users' => $users, 'companies' => DB::table('communication_companies')->orderBy('name')->get(), 'announcement' => PortalContent::announcement()]);
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
            $email = $r->input('email');
            if (is_string($email)) {
                $r->merge(['email' => strtolower($email)]);
            }
            $v = $r->validate(['company_id' => [Rule::requiredIf(fn () => in_array('communication', $r->input('product_slugs', []))), 'nullable', 'integer', 'exists:communication_companies,id'], 'communication_admin' => 'sometimes|boolean', 'name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($id)], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:200'], 'is_active' => 'required|boolean', 'product_slugs' => 'present|array', 'product_slugs.*' => ['string', Rule::in($slugs)]]);
            abort_if(strtolower($v['email']) === strtolower((string) config('judibas.admin_email')), 422, 'This email is reserved for Super Admin.');
            abort_if(! empty($v['communication_admin']) && ! in_array('communication', $v['product_slugs']), 422, 'A Communication Admin needs Communication product access.');
            DB::transaction(function () use ($v, $id) {
                $u = $id ? User::findOrFail($id) : new User;
                $u->name = $v['name'];
                $u->email = strtolower($v['email']);
                $u->is_active = $v['is_active'];
                $u->company_id = $v['company_id'] ?? $u->company_id;
                $u->communication_admin = in_array('communication', $v['product_slugs']) && (bool) ($v['communication_admin'] ?? $u->communication_admin ?? false);
                if (! empty($v['password'])) {
                    $u->password = $v['password'];
                }$u->save();
                DB::table('user_product_access')->where('user_id', $u->id)->delete();
                DB::table('communication_members')->where('user_id', $u->id)->whereIn('conversation_id', DB::table('communication_conversations')->where('kind', 'group')->where('company_id', '!=', $u->company_id)->select('id'))->delete();
                foreach (array_unique($v['product_slugs']) as $slug) {
                    DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => $slug]);
                }
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
        $table = match ($resource) {
            'blogs' => 'blog_posts','users' => 'users',default => null
        };
        abort_unless($table, 404);
        abort_unless(DB::table($table)->where('id', $id)->delete(), 404);

        return response()->json(['message' => 'Deleted.']);
    }
}
