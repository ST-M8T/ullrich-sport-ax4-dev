<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin\Settings\DhlCatalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the service detail controller (PROJ-6 / t15c).
 */
final class DhlCatalogServiceControllerTest extends TestCase
{
    use DhlCatalogControllerTestHelpers;
    use RefreshDatabase;

    private function uri(string $code): string
    {
        return '/admin/settings/dhl/katalog/services/' . $code;
    }

    public function test_redirects_unauthenticated_to_login(): void
    {
        $this->createService('NOT');

        $response = $this->get($this->uri('NOT'));

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_returns_403_for_user_without_view_permission(): void
    {
        $this->createService('NOT');
        $this->actingAs($this->viewerUser());

        $response = $this->get($this->uri('NOT'));

        $response->assertForbidden();
    }

    public function test_returns_200_for_admin_with_existing_service(): void
    {
        $this->createService('NOT');
        $this->actingAs($this->adminUser());

        $response = $this->get($this->uri('NOT'));

        $response->assertOk();
        $response->assertSee('NOT');
    }

    public function test_returns_404_for_unknown_service_code(): void
    {
        $this->actingAs($this->adminUser());

        $response = $this->get($this->uri('UNKNOWN'));

        $response->assertNotFound();
    }
}
