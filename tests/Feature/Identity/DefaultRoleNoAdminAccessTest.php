<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\Authorization\RoleManager;
use App\Application\Identity\UserCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifiziert, dass die konfigurierte Default-Rolle KEIN admin.access erteilt.
 *
 * Hintergrund: Frueher war 'viewer' Default. 'viewer' besitzt 'admin.access'
 * und Lese-Permissions. Das hat neu angelegte User automatisch ins Backend
 * gelassen. Default ist jetzt 'noaccess' (Fail-Closed) und besitzt keine
 * Permissions.
 *
 * Engineering-Handbuch Section 20 (Auth/Authz/Domain trennen),
 * Section 67 (Fail-Fast bei ungueltigem Zustand verhindern).
 */
final class DefaultRoleNoAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_role_is_configured(): void
    {
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);

        self::assertNotNull($roles->defaultRole(), 'Es muss eine Default-Rolle konfiguriert sein.');
    }

    public function test_default_role_has_no_admin_access_permission(): void
    {
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);

        $defaultRole = (string) $roles->defaultRole();

        self::assertFalse(
            $roles->hasPermission($defaultRole, 'admin.access'),
            sprintf("Default-Rolle '%s' darf 'admin.access' NICHT erteilen.", $defaultRole),
        );
    }

    public function test_default_role_has_empty_permission_set(): void
    {
        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);

        $defaultRole = (string) $roles->defaultRole();

        self::assertSame(
            [],
            $roles->permissionsForRole($defaultRole),
            sprintf("Default-Rolle '%s' muss eine leere Permission-Liste haben (Fail-Closed).", $defaultRole),
        );
    }

    public function test_user_created_without_explicit_role_uses_default_and_has_no_admin_access(): void
    {
        /** @var UserCreationService $creator */
        $creator = $this->app->make(UserCreationService::class);

        /** @var RoleManager $roles */
        $roles = $this->app->make(RoleManager::class);

        $user = $creator->create(
            username: 'default_role_user',
            plainPassword: 'Default!23456',
            role: null,
            displayName: 'Default Role User',
            email: 'default-role@example.com',
            requirePasswordChange: false,
            disabled: false,
        );

        self::assertSame($roles->defaultRole(), $user->role());
        self::assertFalse(
            $roles->hasPermission($user->role(), 'admin.access'),
            sprintf("Neu angelegter User mit Default-Rolle '%s' darf KEIN 'admin.access' haben.", $user->role()),
        );
    }
}
