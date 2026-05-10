<?php

namespace App\Providers;

use App\Application\Identity\Authorization\RoleManager;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

final class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [];

    public function boot(): void
    {
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);

        Gate::before(function ($user) use ($roles) {
            // Service-Principal: das admin-token-Guard liefert einen GenericUser
            // mit der ID 'admin-token'. Da ein gueltiger Bearer-Token bereits
            // einen vertrauenswuerdigen Server-zu-Server-Aufruf belegt, gelten
            // hier alle Admin-Permissions. Das spiegelt die Modellierung als
            // service-level credential und haelt Permission-Pruefungen pro
            // Route konsistent (Engineering-Handbuch Section 20).
            if ($user instanceof GenericUser
                && (string) ($user->getAuthIdentifier() ?? '') === 'admin-token'
            ) {
                return true;
            }

            if ($user instanceof UserModel) {
                $permissions = $roles->permissionsForRole($user->role);
                if (in_array('*', $permissions, true)) {
                    return true;
                }
            }

            return null;
        });

        foreach ($roles->allPermissionSlugs() as $permission) {
            Gate::define($permission, static function (?UserModel $user) use ($permission): bool {
                if (! $user instanceof UserModel) {
                    return false;
                }

                return $user->hasPermission($permission);
            });
        }
    }
}
