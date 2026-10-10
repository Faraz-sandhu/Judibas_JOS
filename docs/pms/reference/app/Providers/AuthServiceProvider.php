<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user) {
            // All gates on a request reuse one roles/permissions query set.
            $user->loadMissing('roles.permissions');

            return $user->hasRole('admin') ? true : null;
        });

        if (Schema::hasTable('permissions')) {
            $permissions = Cache::remember('authorization.permission_definitions', now()->addMinutes(10), function () {
                return Permission::query()->get(['id', 'permission_key']);
            });
            foreach ($permissions as $permission) {
                Gate::define($permission->permission_key, function ($user) use ($permission) {
                    $user->loadMissing('roles.permissions');

                    return $user->roles->flatMap(function ($role) {
                        return $role->permissions->pluck('id');
                    })->contains($permission->id);
                });
            }
        }
    }
}
