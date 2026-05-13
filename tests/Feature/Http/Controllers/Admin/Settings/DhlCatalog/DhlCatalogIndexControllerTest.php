<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the catalog overview controller (PROJ-6 / t15c).
 *
 * Engineering-Handbuch §22 / §68: HTTP-Schicht, Permission, Filter,
 * Pagination werden auf Statuscode/Daten geprüft. Views sind Stubs (t16).
 */
final class DhlCatalogIndexControllerTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    private const URI = '/admin/settings/dhl/katalog';

    public function test_redirects_unauthenticated_to_login(): void
    {
        $response = $this->get(self::URI);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_returns_403_for_user_without_dhl_catalog_view_permission(): void
    {
        $this->actingAs($this->viewerUser());

        $response = $this->get(self::URI);

        $response->assertForbidden();
    }

    public function test_returns_200_for_admin(): void
    {
        $this->createProduct('ECI');
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI);

        $response->assertOk();
        $response->assertSee('DHL Katalog');
    }

    public function test_filters_by_status_and_source(): void
    {
        $this->createProduct('ACT', ['source' => DhlCatalogSource::SEED->value]);
        $this->createProduct('DEP', [
            'deprecated_at' => '2026-01-01 00:00:00',
            'source' => DhlCatalogSource::API->value,
        ]);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?status=active');
        $response->assertOk();
        // Stub view renders total — active filter yields 1.
        $response->assertSeeText('Produkte: 1');

        $response2 = $this->get(self::URI . '?status=deprecated&source=api');
        $response2->assertOk();
        $response2->assertSeeText('Produkte: 1');
    }

    public function test_routing_filter_is_applied(): void
    {
        $this->createProduct('DE-AT', ['from_countries' => ['DE'], 'to_countries' => ['AT']]);
        $this->createProduct('FR-IT', ['from_countries' => ['FR'], 'to_countries' => ['IT']]);
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?from_country[]=DE');

        $response->assertOk();
        $response->assertSeeText('Produkte: 1');
    }

    public function test_paginates_results(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->createProduct(sprintf('P%02d', $i), ['name' => 'Bulk ' . $i]);
        }
        $this->actingAs($this->adminUser());

        $response = $this->get(self::URI . '?page=2');

        $response->assertOk();
        // Total stays 30 across pages.
        $response->assertSeeText('Produkte: 30');
    }

    public function test_rejects_invalid_status_filter_value(): void
    {
        $this->actingAs($this->adminUser());

        // The FormRequest validates `status` as in:active,deprecated — anything
        // else is a 422 (technical validation, §15).
        $response = $this->get(self::URI . '?status=garbage');

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['status']);
    }
}
