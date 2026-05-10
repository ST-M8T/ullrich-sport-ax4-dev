<?php

namespace App\Providers;

use App\Application\Identity\AuthenticationService;
use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\Queries\GetUserById;
use App\Application\Identity\Queries\ListRecentLoginAttempts;
use App\Application\Identity\Queries\SearchUsers;
use App\Application\Identity\UserAccountService;
use App\Application\Identity\UserCreationService;
use App\Application\Identity\UserPasswordService;
use App\Application\Identity\UserUpdateService;
use App\Domain\Identity\Contracts\IdentityServiceGateway;
use App\Domain\Identity\Contracts\LoginAttemptRepository;
use App\Domain\Identity\Contracts\PasswordHasher;
use App\Domain\Identity\Contracts\UserRepository as IdentityUserRepository;
use App\Infrastructure\Identity\HttpIdentityServiceGateway;
use App\Infrastructure\Identity\NullIdentityServiceGateway;
use App\Infrastructure\Persistence\Identity\Eloquent\EloquentLoginAttemptRepository;
use App\Infrastructure\Persistence\Identity\Eloquent\EloquentUserRepository;
use App\Infrastructure\Security\BcryptPasswordHasher;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoleManager::class, function ($app): RoleManager {
            $config = $app['config']->get('identity', []);
            $roles = $config['roles'] ?? [];
            $permissions = $config['permissions'] ?? [];
            $defaultRole = $config['defaults']['role'] ?? null;

            return new RoleManager($roles, $permissions, $defaultRole);
        });

        $this->app->bind(IdentityUserRepository::class, EloquentUserRepository::class);
        $this->app->bind(LoginAttemptRepository::class, EloquentLoginAttemptRepository::class);
        $this->app->bind(PasswordHasher::class, BcryptPasswordHasher::class);

        $this->app->singleton(IdentityServiceGateway::class, function ($app) {
            $config = (array) $app['config']->get('services.identity', []);
            $driver = (string) ($config['driver'] ?? '');
            $baseUrl = (string) ($config['base_url'] ?? '');
            $token = $config['token'] ?? null;

            if (($driver === 'http' || ($driver === '' && $baseUrl !== '')) && $baseUrl !== '') {
                return new HttpIdentityServiceGateway($baseUrl, is_string($token) ? $token : null);
            }

            return new NullIdentityServiceGateway;
        });

        $this->app->singleton(UserCreationService::class);
        $this->app->singleton(UserPasswordService::class);
        $this->app->singleton(UserUpdateService::class);
        $this->app->singleton(UserAccountService::class);
        $this->app->singleton(AuthenticationService::class);
        $this->app->singleton(SearchUsers::class);
        $this->app->singleton(GetUserById::class);
        $this->app->singleton(ListRecentLoginAttempts::class);
    }
}
