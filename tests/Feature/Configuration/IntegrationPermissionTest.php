<?php

namespace Tests\Feature\Configuration;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the configuration.integrations.manage permission split.
 *
 * Phase B: Permission-Split -- IntegrationPolicy owns manage action,
 * separate from configuration.settings.manage (Concern-Vermischung).
 */
final class IntegrationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_integrations_route_requires_integrations_manage_permission(): void
    {
        $user = UserModel::query()->create([
            'username' => 'settings-only',
            'display_name' => 'Settings Only User',
            'email' => 'settings@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'configuration',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        // configuration role has configuration.integrations.manage since the split
        $response = $this->get('/admin/configuration/integrations');
        $response->assertOk();
    }

    public function test_integrations_route_denied_without_permission(): void
    {
        $user = UserModel::query()->create([
            'username' => 'viewer',
            'display_name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'viewer',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        // viewer role does NOT have configuration.integrations.manage
        $response = $this->get('/admin/configuration/integrations');
        $response->assertForbidden();
    }

    public function test_operations_role_lacks_integration_permission(): void
    {
        $user = UserModel::query()->create([
            'username' => 'operations',
            'display_name' => 'Operations User',
            'email' => 'ops@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'operations',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        // operations role does NOT have configuration.integrations.manage
        $response = $this->get('/admin/configuration/integrations');
        $response->assertForbidden();
    }

    public function test_leiter_role_has_integration_permission(): void
    {
        $user = UserModel::query()->create([
            'username' => 'leiter',
            'display_name' => 'Leiter User',
            'email' => 'leiter@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'leiter',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        // leiter role has configuration.integrations.manage
        $response = $this->get('/admin/configuration/integrations');
        $response->assertOk();
    }
}
