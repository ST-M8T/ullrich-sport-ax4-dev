<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Infrastructure\Persistence\Configuration\Eloquent\SystemSettingModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Permission-Matrix-Test fuer api/admin/* Endpoints.
 *
 * Verifiziert pro Rolle x Endpoint, dass die can:<permission>-Middleware
 * jeden api/admin/*-Endpoint genauso schuetzt wie das routes/web.php-Pendant.
 * Hintergrund: Vor diesem Fix war auth.admin die einzige Sicherheitsschicht
 * und hat jeden authentifizierten Nutzer (auch viewer/support/operations)
 * Schreibzugriff auf System-Settings und Log-Files erlaubt
 * (Privilege-Escalation, Audit-Trail-Tampering).
 *
 * Engineering-Handbuch Section 19/20: Auth/Authz strikt getrennt, jede
 * Route hat eine Permission-Pruefung; Section 70 Punkt 26: Rechtepruefung
 * niemals nur am Rand.
 */
final class AdminApiPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Endpoints unter api/admin/* mit erwarteter Permission und HTTP-Methode.
     *
     * Erfolgs-Status pro Endpoint kann je nach Implementierung variieren
     * (200/201/204/404). Wichtig fuer den Permission-Test ist nur der
     * Unterschied "darf vs. darf nicht" -> 403 vs. nicht-403.
     *
     * @return array<string, array{method:string, path:string, permission:string}>
     */
    public static function adminEndpoints(): array
    {
        return [
            'GET system-status' => [
                'method' => 'getJson',
                'path' => '/api/admin/system-status',
                'permission' => 'admin.setup.view',
            ],
            'GET system-settings' => [
                'method' => 'getJson',
                'path' => '/api/admin/system-settings',
                'permission' => 'configuration.settings.manage',
            ],
            'POST system-settings' => [
                'method' => 'postJson',
                'path' => '/api/admin/system-settings',
                'permission' => 'configuration.settings.manage',
            ],
            'GET system-settings/{key}' => [
                'method' => 'getJson',
                'path' => '/api/admin/system-settings/some_key',
                'permission' => 'configuration.settings.manage',
            ],
            'PATCH system-settings/{key}' => [
                'method' => 'patchJson',
                'path' => '/api/admin/system-settings/some_key',
                'permission' => 'configuration.settings.manage',
            ],
            'DELETE system-settings/{key}' => [
                'method' => 'deleteJson',
                'path' => '/api/admin/system-settings/some_key',
                'permission' => 'configuration.settings.manage',
            ],
            'GET log-files' => [
                'method' => 'getJson',
                'path' => '/api/admin/log-files',
                'permission' => 'admin.logs.view',
            ],
            'GET log-files/{file}/entries' => [
                'method' => 'getJson',
                'path' => '/api/admin/log-files/admin-matrix-test.log/entries',
                'permission' => 'admin.logs.view',
            ],
            'POST log-files/{file}/actions/download' => [
                'method' => 'postJson',
                'path' => '/api/admin/log-files/admin-matrix-test.log/actions/download',
                'permission' => 'admin.logs.view',
            ],
            'DELETE log-files/{file}' => [
                'method' => 'deleteJson',
                'path' => '/api/admin/log-files/admin-matrix-test.log',
                'permission' => 'admin.logs.view',
            ],
        ];
    }

    /**
     * Permissions pro Rolle (gespiegelt aus config/identity.php).
     *
     * @return array<string, array<int, string>>
     */
    private static function rolePermissions(): array
    {
        return [
            'admin' => ['*'],
            'leiter' => [
                'admin.access', 'admin.logs.view', 'admin.setup.view',
                'configuration.mail_templates.manage', 'configuration.notifications.manage',
                'dispatch.lists.manage', 'fulfillment.csv_export.manage',
                'fulfillment.masterdata.manage', 'fulfillment.orders.view',
                'fulfillment.shipments.manage', 'identity.users.manage',
                'monitoring.audit_logs.view', 'monitoring.domain_events.view',
                'monitoring.system_jobs.view', 'tracking.alerts.manage',
                'tracking.jobs.manage', 'tracking.overview.view',
            ],
            'operations' => [
                'admin.access', 'dispatch.lists.manage', 'fulfillment.csv_export.manage',
                'fulfillment.masterdata.manage', 'fulfillment.orders.view',
                'fulfillment.shipments.manage', 'monitoring.domain_events.view',
                'monitoring.system_jobs.view', 'tracking.alerts.manage',
                'tracking.jobs.manage', 'tracking.overview.view',
            ],
            'support' => [
                'admin.access', 'admin.logs.view',
                'monitoring.audit_logs.view', 'monitoring.domain_events.view',
                'monitoring.system_jobs.view', 'tracking.overview.view',
            ],
            'configuration' => [
                'admin.access', 'admin.setup.view',
                'configuration.mail_templates.manage', 'configuration.notifications.manage',
                'configuration.settings.manage',
            ],
            'identity' => [
                'admin.access', 'identity.users.manage',
            ],
            'viewer' => [
                'admin.access', 'fulfillment.orders.view',
                'monitoring.system_jobs.view', 'tracking.overview.view',
            ],
        ];
    }

    /**
     * Liefert das kartesische Produkt aus Rollen x Endpoints, damit die
     * Permission-Matrix als einzelner DataProvider getestet werden kann.
     *
     * @return array<string, array{role:string, method:string, path:string, permission:string, expectsAllowed:bool}>
     */
    public static function rolePermissionMatrix(): array
    {
        $matrix = [];
        foreach (self::rolePermissions() as $role => $permissions) {
            foreach (self::adminEndpoints() as $endpointLabel => $endpoint) {
                $expectsAllowed = in_array('*', $permissions, true)
                    || in_array($endpoint['permission'], $permissions, true);

                $matrix[$role.' -> '.$endpointLabel] = [
                    'role' => $role,
                    'method' => $endpoint['method'],
                    'path' => $endpoint['path'],
                    'permission' => $endpoint['permission'],
                    'expectsAllowed' => $expectsAllowed,
                ];
            }
        }

        return $matrix;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Admin-Token wird in diesen Tests nicht verwendet (wir testen ueber
        // session-basierte UserModel-Authentifizierung). Dennoch konfiguriert,
        // damit die Bootstrapping-Reihenfolge stabil bleibt.
        config(['services.admin_api.token' => 'matrix-test-token']);

        // Fixture: log-file und system-setting, damit Routen nicht aufgrund
        // fehlender Resourcen 404 liefern und so den Permission-Check
        // verschleiern.
        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        File::put($logDir.DIRECTORY_SEPARATOR.'admin-matrix-test.log', "matrix\n");

        SystemSettingModel::query()->updateOrCreate(
            ['setting_key' => 'some_key'],
            [
                'setting_value' => 'value',
                'value_type' => 'string',
                'updated_at' => now(),
            ],
        );
    }

    protected function tearDown(): void
    {
        $logFile = storage_path('logs').DIRECTORY_SEPARATOR.'admin-matrix-test.log';
        if (is_file($logFile)) {
            @unlink($logFile);
        }

        parent::tearDown();
    }

    #[DataProvider('rolePermissionMatrix')]
    public function test_admin_api_endpoint_enforces_permission_per_role(
        string $role,
        string $method,
        string $path,
        string $permission,
        bool $expectsAllowed,
    ): void {
        $this->signInWithRole($role);

        $response = $this->{$method}($path, $this->payloadFor($method, $path));

        if ($expectsAllowed) {
            self::assertNotSame(
                403,
                $response->getStatusCode(),
                sprintf(
                    "Rolle '%s' (Permission '%s') sollte Zugriff auf %s %s haben, wurde aber mit 403 abgelehnt.",
                    $role,
                    $permission,
                    strtoupper(str_replace('Json', '', $method)),
                    $path,
                ),
            );

            return;
        }

        self::assertSame(
            403,
            $response->getStatusCode(),
            sprintf(
                "Rolle '%s' (ohne '%s') haette 403 erhalten muessen fuer %s %s, bekam aber %d.",
                $role,
                $permission,
                strtoupper(str_replace('Json', '', $method)),
                $path,
                $response->getStatusCode(),
            ),
        );
    }

    public function test_unauthenticated_request_to_admin_api_returns_401(): void
    {
        $response = $this->getJson('/api/admin/system-settings');

        $response->assertStatus(401);
    }

    public function test_admin_token_principal_bypasses_permission_check(): void
    {
        // Der admin-token-Guard liefert einen GenericUser. Server-zu-Server-
        // Zugriff darf weiterhin alle api/admin/*-Endpoints aufrufen, da der
        // Token selbst die Vertrauensgrenze ist.
        $response = $this->withHeaders(['Authorization' => 'Bearer matrix-test-token'])
            ->getJson('/api/admin/system-settings');

        $response->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $method, string $path): array
    {
        // POST und PATCH brauchen ein minimal valides Payload, sonst stuft
        // FormRequest 422 vor 403 ein und der Permission-Test waere blind.
        // Bei korrekter Permission antwortet der Controller dann je nach
        // Validierung mit einem eigenen Status (oft 422); 403 bleibt bei
        // fehlender Permission die zuverlaessige Signalantwort.
        if ($method === 'postJson' && str_ends_with($path, '/system-settings')) {
            return [
                'data' => [
                    'type' => 'system-settings',
                    'attributes' => [
                        'key' => 'matrix_'.uniqid(),
                        'value' => 'value',
                        'value_type' => 'string',
                    ],
                ],
            ];
        }

        if ($method === 'patchJson' && str_contains($path, '/system-settings/')) {
            return [
                'data' => [
                    'type' => 'system-settings',
                    'id' => 'some_key',
                    'attributes' => [
                        'value' => 'value',
                        'value_type' => 'string',
                    ],
                ],
            ];
        }

        return [];
    }
}
