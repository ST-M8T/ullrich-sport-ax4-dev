<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Application\Fulfillment\Integrations\Dhl\Catalog\DhlCatalogAuditLogger;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the audit-log controller (PROJ-6 / t15c).
 */
final class DhlCatalogAuditControllerTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    private const URI = '/admin/settings/dhl/katalog/audit';

    public function test_redirects_unauthenticated_to_login(): void
    {
        $response = $this->get(self::URI);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_returns_403_for_user_without_audit_read_permission(): void
    {
        // operations role has dhl-catalog.view but NOT dhl-catalog.audit.read.
        $this->actingAs($this->operationsUser());

        $response = $this->get(self::URI);

        $response->assertForbidden();
    }

    public function test_returns_200_for_admin(): void
    {
        $this->createAuditEntry();
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertSeeText('Einträge: 1');
    }

    public function test_filters_by_entity_type(): void
    {
        $this->createAuditEntry(['entity_type' => DhlCatalogAuditLogger::ENTITY_PRODUCT]);
        $this->createAuditEntry(['entity_type' => DhlCatalogAuditLogger::ENTITY_SERVICE]);
        $this->createAuditEntry(['entity_type' => DhlCatalogAuditLogger::ENTITY_ASSIGNMENT]);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?entity_type=service');

        $response->assertOk();
        $response->assertSeeText('Einträge: 1');
    }

    public function test_filters_by_action(): void
    {
        $this->createAuditEntry(['action' => DhlCatalogAuditLogger::ACTION_CREATED]);
        $this->createAuditEntry(['action' => DhlCatalogAuditLogger::ACTION_UPDATED]);
        $this->createAuditEntry(['action' => DhlCatalogAuditLogger::ACTION_DEPRECATED]);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?action=deprecated');

        $response->assertOk();
        $response->assertSeeText('Einträge: 1');
    }

    public function test_filters_by_actor(): void
    {
        $this->createAuditEntry(['actor' => 'system:dhl-sync']);
        $this->createAuditEntry(['actor' => 'user:alice@example.com']);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?actor=alice');

        $response->assertOk();
        $response->assertSeeText('Einträge: 1');
    }

    public function test_filters_by_time_range(): void
    {
        $this->createAuditEntry(['created_at' => new DateTimeImmutable('2026-01-01 12:00:00')]);
        $this->createAuditEntry(['created_at' => new DateTimeImmutable('2026-03-01 12:00:00')]);
        $this->createAuditEntry(['created_at' => new DateTimeImmutable('2026-06-01 12:00:00')]);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?from=2026-02-01T00:00&to=2026-05-01T00:00');

        $response->assertOk();
        $response->assertSeeText('Einträge: 1');
    }

    public function test_rejects_invalid_entity_type_filter(): void
    {
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?entity_type=garbage');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['entity_type']);
    }
}
