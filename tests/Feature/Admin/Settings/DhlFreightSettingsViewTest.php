<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Settings;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * View-Tests fuer die konsolidierte DHL-Freight Settings-Seite (Task t11).
 *
 * Engineering-Handbuch §58: kritische UI-Strukturen werden auf vorhandene
 * Sections, Form-Felder, CSRF und Method-Spoofing geprueft. Snapshot-Tests
 * allein reichen nicht (§58, letzter Satz).
 */
final class DhlFreightSettingsViewTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-view-admin',
            'display_name' => 'DHL View Admin',
            'email' => 'dhl-view-admin@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    private function bindRepository(DhlConfiguration $configuration): void
    {
        $this->app->instance(
            DhlConfigurationRepository::class,
            new class($configuration) implements DhlConfigurationRepository
            {
                public function __construct(private DhlConfiguration $configuration) {}

                public function load(): DhlConfiguration
                {
                    return $this->configuration;
                }

                public function save(DhlConfiguration $configuration): void
                {
                    $this->configuration = $configuration;
                }
            }
        );
    }

    private function configuredConfig(): DhlConfiguration
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: 'client-id-123',
            authClientSecret: 'SECRET',
            freightBaseUrl: 'https://freight.example.com',
            freightApiKey: 'freight-key',
            freightApiSecret: 'FREIGHT-SECRET',
        );
        $config->setTimeoutSeconds(30);
        $config->setVerifySsl(true);

        return $config;
    }

    public function test_view_renders_all_section_headers(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('A. API-Authentifizierung', false);
        $response->assertSee('B. Freight-API', false);
        $response->assertSee('C. Standard-Konfiguration', false);
        $response->assertSee('D. Tracking', false);
        $response->assertSee('E. Push-Webhook', false);
    }

    public function test_view_contains_csrf_token_and_put_method_spoofing(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="_method"', false);
        $response->assertSee('value="PUT"', false);
    }

    public function test_view_renders_all_required_input_names(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $expectedNames = [
            'auth_base_url',
            'auth_client_id',
            'auth_client_secret',
            'freight_base_url',
            'freight_api_key',
            'freight_api_secret',
            'default_account_number',
            'tracking_api_key',
            'tracking_default_service',
            'tracking_origin_country_code',
            'tracking_requester_country_code',
            'timeout_seconds',
            'verify_ssl',
            'push_base_url',
            'push_api_key',
        ];

        foreach ($expectedNames as $name) {
            $response->assertSee('name="'.$name.'"', false);
        }
    }

    public function test_view_shows_set_placeholder_when_secret_is_present(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('gesetzt');
        $response->assertDontSee('SECRET');
        $response->assertDontSee('FREIGHT-SECRET');
    }

    public function test_view_shows_not_set_placeholder_for_unset_secret(): void
    {
        // Push API-Key ist standardmaessig nicht gesetzt.
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('nicht gesetzt');
    }

    public function test_view_renders_test_connection_button(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('data-dhl-freight-test-connection', false);
        $response->assertSee('Verbindung testen');
    }

    public function test_view_does_not_show_initial_setup_hint_when_configured(): void
    {
        $this->bindRepository($this->configuredConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertDontSee('dhl-freight-initial-setup-hint', false);
    }
}
