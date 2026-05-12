<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifiziert, dass die alten verstreuten DHL-Integrations-Routen
 * nach Versand → DHL Freight (admin.settings.dhl-freight.index)
 * umgeleitet werden.
 *
 * Engineering-Handbuch §72: bestehende Schnittstellen respektieren —
 * Bookmarks/Deep-Links der alten Routen müssen weiter funktionieren,
 * landen aber auf der konsolidierten Settings-Seite (§75: eine Stelle
 * pro UI-Muster).
 */
final class DhlIntegrationRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsConfigurationAdmin(): UserModel
    {
        $user = UserModel::query()->create([
            'username' => 'config-admin',
            'display_name' => 'Configuration Admin',
            'email' => 'config@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'configuration',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_show_dhl_freight_integration_redirects_to_consolidated_settings(): void
    {
        $this->actingAsConfigurationAdmin();

        $response = $this->get('/admin/configuration/integrations/dhl_freight');

        $response->assertRedirect(route('admin.settings.dhl-freight.index'));
        $this->assertNotNull(session('info'));
    }

    public function test_update_dhl_freight_integration_redirects_to_consolidated_settings(): void
    {
        $this->actingAsConfigurationAdmin();

        $response = $this->post('/admin/configuration/integrations/dhl_freight', [
            'configuration' => ['dhl_freight_base_url' => 'https://example'],
        ]);

        $response->assertRedirect(route('admin.settings.dhl-freight.index'));
    }

    public function test_test_endpoint_for_dhl_freight_redirects_to_consolidated_settings(): void
    {
        $this->actingAsConfigurationAdmin();

        $response = $this->post('/admin/configuration/integrations/dhl_freight/test', [
            'configuration' => ['dhl_freight_base_url' => 'https://example'],
        ]);

        $response->assertRedirect(route('admin.settings.dhl-freight.index'));
    }

    public function test_dhl_freight_card_is_hidden_from_integrations_index(): void
    {
        $this->actingAsConfigurationAdmin();

        $response = $this->get('/admin/configuration/integrations');

        $response->assertOk();
        $response->assertSee('Versand → DHL Freight', escape: false);
        $response->assertDontSee(route('configuration-integrations.show', ['integrationKey' => 'dhl_freight']));
    }

    public function test_dhl_settings_tab_renders_redirect_hint_instead_of_form(): void
    {
        $this->actingAsConfigurationAdmin();

        $response = $this->get('/admin/configuration/settings?tab=settings&settings_group=dhl');

        $response->assertOk();
        $response->assertSee('Versand → DHL Freight', escape: false);
        // Kein Speichern-Button für die DHL-Gruppe mehr.
        $response->assertDontSee('DHL-Einstellungen speichern');
    }
}
