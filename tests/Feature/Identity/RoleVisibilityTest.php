<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\Authorization\RoleManager;
use Tests\TestCase;

/**
 * Sichtbarkeits-Test pro Rolle.
 *
 * Prüft die erwartete Anzahl Berechtigungen pro Rolle gegen den `RoleManager`.
 * Dieser Test ist die Letzte-Verteidigungslinie: er schlägt zuerst aus, wenn
 * sich die Rechte-Reichweite einer Rolle ändert. Die Erwartungswerte sind
 * dann bewusst zu aktualisieren — sie spiegeln den fachlichen Vertrag
 * mit Identity / Product Owner wider.
 *
 * Quelle der Wahrheit: config/identity.php.
 *
 * @see docs/SYSTEM_CLEANUP_BACKLOG.md ID-1
 */
final class RoleVisibilityTest extends TestCase
{
    /**
     * Pro Rolle: erwartete Mindest-Permission-Set.
     *
     * Gemessen wird gegen `RoleManager::hasPermission(role, permission)`.
     * Der Test darf neue Permissions nicht ausschließen, nur fehlende erkennen.
     *
     * @return array<string, array{permissions: array<int, string>, blocked: array<int, string>}>
     */
    public static function rolePermissionContract(): array
    {
        return [
            'admin' => [
                'permissions' => [
                    'admin.access',
                    'admin.logs.view',
                    'admin.setup.view',
                    'configuration.mail_templates.manage',
                    'configuration.notifications.manage',
                    'configuration.settings.manage',
                    'dispatch.lists.manage',
                    'fulfillment.csv_export.manage',
                    'fulfillment.masterdata.manage',
                    'fulfillment.orders.view',
                    'fulfillment.shipments.manage',
                    'identity.users.manage',
                    'monitoring.audit_logs.view',
                    'monitoring.domain_events.view',
                    'monitoring.system_jobs.view',
                    'tracking.alerts.manage',
                    'tracking.jobs.manage',
                    'tracking.overview.view',
                ],
                'blocked' => [],
            ],
            'leiter' => [
                'permissions' => [
                    'admin.access',
                    'admin.logs.view',
                    'admin.setup.view',
                    'configuration.mail_templates.manage',
                    'configuration.notifications.manage',
                    'dispatch.lists.manage',
                    'fulfillment.csv_export.manage',
                    'fulfillment.masterdata.manage',
                    'fulfillment.orders.view',
                    'fulfillment.shipments.manage',
                    'identity.users.manage',
                    'monitoring.audit_logs.view',
                    'monitoring.domain_events.view',
                    'monitoring.system_jobs.view',
                    'tracking.alerts.manage',
                    'tracking.jobs.manage',
                    'tracking.overview.view',
                ],
                'blocked' => [
                    // Leiter darf keine Systemeinstellungen schreiben
                    'configuration.settings.manage',
                ],
            ],
            'operations' => [
                'permissions' => [
                    'admin.access',
                    'dispatch.lists.manage',
                    'fulfillment.csv_export.manage',
                    'fulfillment.masterdata.manage',
                    'fulfillment.orders.view',
                    'fulfillment.shipments.manage',
                    'monitoring.domain_events.view',
                    'monitoring.system_jobs.view',
                    'tracking.alerts.manage',
                    'tracking.jobs.manage',
                    'tracking.overview.view',
                ],
                'blocked' => [
                    'admin.logs.view',
                    'admin.setup.view',
                    'configuration.mail_templates.manage',
                    'configuration.notifications.manage',
                    'configuration.settings.manage',
                    'identity.users.manage',
                    'monitoring.audit_logs.view',
                ],
            ],
            'support' => [
                'permissions' => [
                    'admin.access',
                    'admin.logs.view',
                    'monitoring.audit_logs.view',
                    'monitoring.domain_events.view',
                    'monitoring.system_jobs.view',
                    'tracking.overview.view',
                ],
                'blocked' => [
                    'configuration.settings.manage',
                    'fulfillment.orders.view',
                    'identity.users.manage',
                    'tracking.alerts.manage',
                    'tracking.jobs.manage',
                ],
            ],
            'configuration' => [
                'permissions' => [
                    'admin.access',
                    'admin.setup.view',
                    'configuration.mail_templates.manage',
                    'configuration.notifications.manage',
                    'configuration.settings.manage',
                ],
                'blocked' => [
                    'fulfillment.orders.view',
                    'identity.users.manage',
                    'monitoring.audit_logs.view',
                ],
            ],
            'identity' => [
                'permissions' => [
                    'admin.access',
                    'identity.users.manage',
                ],
                'blocked' => [
                    'configuration.settings.manage',
                    'fulfillment.orders.view',
                    'tracking.overview.view',
                ],
            ],
            'viewer' => [
                'permissions' => [
                    'admin.access',
                    'fulfillment.orders.view',
                    'monitoring.system_jobs.view',
                    'tracking.overview.view',
                ],
                'blocked' => [
                    'configuration.settings.manage',
                    'fulfillment.shipments.manage',
                    'identity.users.manage',
                    'tracking.alerts.manage',
                ],
            ],
        ];
    }

    public function test_role_manager_knows_all_configured_roles(): void
    {
        $config = require config_path('identity.php');
        $expectedRoles = array_keys($config['roles']);

        /** @var RoleManager $manager */
        $manager = $this->app->make(RoleManager::class);

        // 'noaccess' ist die explizit leere Default-Rolle (Fail-Closed). Sie
        // darf keine Permissions besitzen und wird hier bewusst ausgenommen.
        $rolesWithExpectedPermissions = array_filter(
            $expectedRoles,
            static fn (string $role): bool => $role !== 'noaccess',
        );

        foreach ($rolesWithExpectedPermissions as $role) {
            self::assertNotEmpty(
                $manager->permissionsForRole($role),
                "Rolle '{$role}' liefert keine Berechtigungen über RoleManager."
            );
        }

        self::assertContains('noaccess', $expectedRoles, "Default-Rolle 'noaccess' muss in der Konfiguration existieren.");
        self::assertSame([], $manager->permissionsForRole('noaccess'), "Rolle 'noaccess' darf keine Permissions besitzen.");

        // Sicherheits-Smoke-Test: Keine unbekannte Rolle darf Berechtigungen erhalten.
        self::assertSame([], $manager->permissionsForRole('rolle-existiert-nicht'));
    }

    public function test_each_role_grants_its_contracted_permissions(): void
    {
        /** @var RoleManager $manager */
        $manager = $this->app->make(RoleManager::class);

        foreach (self::rolePermissionContract() as $role => $expectations) {
            foreach ($expectations['permissions'] as $permission) {
                self::assertTrue(
                    $manager->hasPermission($role, $permission),
                    "Rolle '{$role}' sollte '{$permission}' besitzen, hat sie aber nicht."
                );
            }
        }
    }

    public function test_each_role_does_not_grant_blocked_permissions(): void
    {
        /** @var RoleManager $manager */
        $manager = $this->app->make(RoleManager::class);

        foreach (self::rolePermissionContract() as $role => $expectations) {
            foreach ($expectations['blocked'] as $permission) {
                self::assertFalse(
                    $manager->hasPermission($role, $permission),
                    "Rolle '{$role}' darf '{$permission}' nicht haben, hat sie aber."
                );
            }
        }
    }

    public function test_admin_is_the_only_wildcard_role(): void
    {
        /** @var RoleManager $manager */
        $manager = $this->app->make(RoleManager::class);

        $config = require config_path('identity.php');
        foreach ($config['roles'] as $role => $definition) {
            $hasWildcard = in_array('*', $definition['permissions'] ?? [], true);
            if ($role === 'admin') {
                self::assertTrue($hasWildcard, "Rolle 'admin' sollte das Wildcard '*' besitzen.");
            } else {
                self::assertFalse(
                    $hasWildcard,
                    "Rolle '{$role}' darf das Wildcard '*' nicht besitzen — nur 'admin' ist dazu berechtigt."
                );
            }
        }

        // Smoke-Test: Admin darf alles, was anderen Rollen verboten ist.
        foreach (['configuration.settings.manage', 'identity.users.manage', 'monitoring.audit_logs.view'] as $permission) {
            self::assertTrue(
                $manager->hasPermission('admin', $permission),
                "Admin sollte '{$permission}' besitzen, da Wildcard."
            );
        }
    }

    public function test_persona_hierarchy_admin_strictly_dominates_leiter_strictly_dominates_operations(): void
    {
        /** @var RoleManager $manager */
        $manager = $this->app->make(RoleManager::class);

        $allPermissions = array_keys((require config_path('identity.php'))['permissions']);

        $adminCount = 0;
        $leiterCount = 0;
        $operationsCount = 0;

        foreach ($allPermissions as $permission) {
            $adminCount += $manager->hasPermission('admin', $permission) ? 1 : 0;
            $leiterCount += $manager->hasPermission('leiter', $permission) ? 1 : 0;
            $operationsCount += $manager->hasPermission('operations', $permission) ? 1 : 0;
        }

        self::assertGreaterThan($leiterCount, $adminCount, 'Admin muss strikt mehr Permissions als Leiter haben.');
        self::assertGreaterThan($operationsCount, $leiterCount, 'Leiter muss strikt mehr Permissions als Operations haben.');
    }
}
