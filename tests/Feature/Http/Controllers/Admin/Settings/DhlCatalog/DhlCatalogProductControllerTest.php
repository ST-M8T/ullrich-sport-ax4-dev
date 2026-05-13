<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the product detail controller (PROJ-6 / t15c).
 */
final class DhlCatalogProductControllerTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    private function uri(string $code): string
    {
        return '/admin/settings/dhl/katalog/produkte/' . $code;
    }

    public function test_redirects_unauthenticated_to_login(): void
    {
        $this->createProduct('ECI');

        $response = $this->get($this->uri('ECI'));

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_returns_403_for_user_without_view_permission(): void
    {
        $this->createProduct('ECI');
        $this->actingAs($this->viewerUser());

        $response = $this->get($this->uri('ECI'));

        $response->assertForbidden();
    }

    public function test_returns_200_for_admin_with_existing_product(): void
    {
        $this->createProduct('ECI');
        $this->createService('NOT');
        $this->createAssignment('ECI', 'NOT');
        $this->actingAs($this->adminUser());

        $response = $this->get($this->uri('ECI'));

        $response->assertOk();
        $response->assertSee('ECI');
    }

    public function test_returns_404_for_unknown_product_code(): void
    {
        $this->actingAs($this->adminUser());

        $response = $this->get($this->uri('ZZZ'));

        $response->assertNotFound();
    }

    public function test_returns_404_for_invalid_product_code_format(): void
    {
        $this->actingAs($this->adminUser());

        // DhlProductCode VO throws on invalid format → controller maps to 404.
        $response = $this->get($this->uri('not-a-valid-code-because-too-long-xxx'));

        $response->assertNotFound();
    }
}
