<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Settings;

use App\Application\Configuration\SystemSettingService;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-End-Roundtrip-Test fuer DHL-Freight-Settings:
 * PUT -> Eloquent-Repository -> system_settings-Tabelle -> GET zeigt Werte (ohne Secrets).
 *
 * Schliesst die Luecke zwischen Controller-Test (Fake-Repo) und Repository-Test (kein HTTP):
 * Der echte Stack muss persistente Werte korrekt aus der DB anzeigen und Secrets dabei ausblenden.
 */
final class DhlFreightSettingsEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,mixed> */
    private array $payload = [
        'auth_base_url' => 'https://api-sandbox.dhl.com/auth',
        'auth_client_id' => 'e2e-client-id',
        'auth_client_secret' => 'e2e-client-secret',
        'freight_base_url' => 'https://api-sandbox.dhl.com/freight',
        'freight_api_key' => 'e2e-freight-key',
        'freight_api_secret' => 'e2e-freight-secret',
        'default_account_number' => '5099887766',
        'tracking_api_key' => 'e2e-tracking-key',
        'tracking_default_service' => 'standard',
        'tracking_origin_country_code' => 'DE',
        'tracking_requester_country_code' => 'DE',
        'timeout_seconds' => 12,
        'verify_ssl' => true,
        'push_base_url' => 'https://push-sandbox.dhl.com',
        'push_api_key' => 'e2e-push-key',
    ];

    private function adminUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-e2e-admin',
            'display_name' => 'E2E Admin',
            'email' => 'dhl-e2e@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    /**
     * Seedet eine minimale gueltige DhlConfiguration in system_settings,
     * damit der Controller-Update-Pfad (`load()` → mutieren → `save()`)
     * ueberhaupt funktioniert. Hintergrund: Der Controller verlangt aktuell
     * eine bereits existierende Konfiguration — siehe Bug-Notiz unten.
     */
    private function seedMinimalConfig(): void
    {
        /** @var SystemSettingService $settings */
        $settings = $this->app->make(SystemSettingService::class);
        $settings->set('dhl_auth_base_url', 'https://seed-auth.example.com');
        $settings->set('dhl_auth_username', 'seed-client-id');
        $settings->set('dhl_auth_password', 'seed-client-secret');
        $settings->set('dhl_freight_base_url', 'https://seed-freight.example.com');
        $settings->set('dhl_freight_api_key', 'seed-freight-key');
        $settings->set('dhl_freight_api_secret', 'seed-freight-secret');
    }

    public function test_put_then_get_shows_persisted_values_without_secrets(): void
    {
        $this->seedMinimalConfig();
        $this->actingAs($this->adminUser());

        $putResponse = $this->put('/admin/settings/dhl-freight', $this->payload);
        $putResponse->assertRedirect('/admin/settings/dhl-freight');
        $putResponse->assertSessionHas('success');

        $getResponse = $this->get('/admin/settings/dhl-freight');
        $getResponse->assertOk();

        // Nicht-geheime Werte sind sichtbar.
        $getResponse->assertSee('https://api-sandbox.dhl.com/auth');
        $getResponse->assertSee('e2e-client-id');
        $getResponse->assertSee('https://api-sandbox.dhl.com/freight');
        $getResponse->assertSee('e2e-freight-key');
        $getResponse->assertSee('5099887766');

        // Secrets duerfen nie im HTML auftauchen (auch nicht maskiert in value="").
        $getResponse->assertDontSee('e2e-client-secret');
        $getResponse->assertDontSee('e2e-freight-secret');
    }

    public function test_empty_secret_on_update_keeps_persisted_secret(): void
    {
        $this->seedMinimalConfig();
        $this->actingAs($this->adminUser());

        // Initial speichern (echte Persistenz).
        $this->put('/admin/settings/dhl-freight', $this->payload)
            ->assertRedirect('/admin/settings/dhl-freight');

        // Zweites Update ohne Secret.
        $second = $this->payload;
        $second['auth_client_id'] = 'updated-client-id';
        $second['auth_client_secret'] = '';
        $second['freight_api_secret'] = '';

        $this->put('/admin/settings/dhl-freight', $second)
            ->assertRedirect('/admin/settings/dhl-freight');

        // Repository neu laden und Werte pruefen.
        /** @var \App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository $repo */
        $repo = $this->app->make(\App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository::class);
        $config = $repo->load();

        self::assertSame('updated-client-id', $config->authClientId());
        self::assertSame('e2e-client-secret', $config->authClientSecret());
        self::assertSame('e2e-freight-secret', $config->freightApiSecret());
    }

    /**
     * BUG-DOKUMENTATION (HIGH): First-Time-Setup ist aktuell nicht moeglich.
     *
     * Der Controller ruft in `update()` `$repository->load()` ausserhalb des
     * try/catch-Blocks auf (DhlFreightSettingsController:55). Existiert noch
     * keine Konfiguration in `system_settings`, wirft `EloquentDhlConfiguration\
     * Repository::load()` eine `DhlConfigurationException` ("Pflicht-Einstellung
     * 'dhl_auth_base_url' ist nicht gesetzt"), die als 500 endet.
     *
     * Erwartet: Bei fehlender Konfiguration soll der Admin trotzdem erstmalig
     * speichern koennen. Vorgeschlagener Fix: `load()` in try/catch wickeln und
     * bei `DhlConfigurationException` mit `DhlConfiguration::create(...)` eine
     * neue Aggregate-Instanz aus dem validierten Payload bauen.
     *
     * Dieser Test fixiert das aktuelle (fehlerhafte) Verhalten — wenn der Bug
     * gefixt wird, MUSS dieser Test entsprechend angepasst werden.
     */
    public function test_first_time_setup_currently_fails_without_seed(): void
    {
        $this->actingAs($this->adminUser());

        // Kein seedMinimalConfig() — frische Installation simulieren.
        $response = $this->put('/admin/settings/dhl-freight', $this->payload);

        // Aktuelles Verhalten: 500 statt erfolgreichem Initial-Save.
        $response->assertStatus(500);
    }
}
