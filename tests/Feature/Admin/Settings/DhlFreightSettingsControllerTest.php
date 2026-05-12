<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Settings;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature-Tests fuer den konsolidierten DHL-Freight Settings-Controller (Task t10).
 *
 * Engineering-Handbuch §68: API/Routen-Tests pruefen Berechtigung,
 * Validierung, Erfolgs- und Fehlerpfade.
 */
final class DhlFreightSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,mixed> */
    private array $validPayload = [
        'auth_base_url' => 'https://api-sandbox.dhl.com/auth',
        'auth_client_id' => 'client-id-123',
        'auth_client_secret' => 'client-secret-456',
        'freight_base_url' => 'https://api-sandbox.dhl.com/freight',
        'freight_api_key' => 'freight-key-789',
        'freight_api_secret' => 'freight-secret-abc',
        'default_account_number' => '5012345678',
        'tracking_api_key' => 'tracking-key-xyz',
        'tracking_default_service' => 'standard',
        'tracking_origin_country_code' => 'DE',
        'tracking_requester_country_code' => 'DE',
        'timeout_seconds' => 15,
        'verify_ssl' => true,
        'push_base_url' => 'https://push-sandbox.dhl.com/notifications',
        'push_api_key' => 'push-key-555',
    ];

    private function adminUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'dhl-admin',
            'display_name' => 'DHL Admin',
            'email' => 'dhl-admin@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'admin',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    private function viewerUser(): UserModel
    {
        return UserModel::query()->create([
            'username' => 'viewer-only',
            'display_name' => 'Viewer Only',
            'email' => 'viewer-only@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'viewer',
            'must_change_password' => false,
            'disabled' => false,
        ]);
    }

    private function bindRepository(DhlConfiguration $configuration, ?\Closure $onSave = null): void
    {
        $this->app->instance(
            DhlConfigurationRepository::class,
            new class($configuration, $onSave) implements DhlConfigurationRepository
            {
                public function __construct(
                    private DhlConfiguration $configuration,
                    private ?\Closure $onSave,
                ) {}

                public function load(): DhlConfiguration
                {
                    return $this->configuration;
                }

                public function save(DhlConfiguration $configuration): void
                {
                    $this->configuration = $configuration;
                    if ($this->onSave) {
                        ($this->onSave)($configuration);
                    }
                }
            }
        );
    }

    private function defaultConfig(): DhlConfiguration
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://existing-auth.example.com',
            authClientId: 'existing-client-id',
            authClientSecret: 'EXISTING-SECRET-DO-NOT-CHANGE',
            freightBaseUrl: 'https://existing-freight.example.com',
            freightApiKey: 'existing-freight-key',
            freightApiSecret: 'EXISTING-FREIGHT-SECRET-DO-NOT-CHANGE',
        );
        $config->setTimeoutSeconds(10);
        $config->setVerifySsl(true);

        return $config;
    }

    public function test_index_redirects_unauthenticated_user_to_login(): void
    {
        $this->bindRepository($this->defaultConfig());

        $response = $this->get('/admin/settings/dhl-freight');

        // Unauthenticated wird durch 'auth' Middleware auf Login umgeleitet (302).
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_index_forbidden_for_user_without_permission(): void
    {
        $this->bindRepository($this->defaultConfig());
        $this->actingAs($this->viewerUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertForbidden();
    }

    public function test_index_returns_view_with_current_configuration(): void
    {
        $this->bindRepository($this->defaultConfig());
        $this->actingAs($this->adminUser());

        $response = $this->get('/admin/settings/dhl-freight');

        $response->assertOk();
        $response->assertSee('https://existing-auth.example.com');
        $response->assertSee('existing-client-id');
        // Secrets duerfen nicht in der Ansicht stehen.
        $response->assertDontSee('EXISTING-SECRET-DO-NOT-CHANGE');
        $response->assertDontSee('EXISTING-FREIGHT-SECRET-DO-NOT-CHANGE');
    }

    public function test_update_persists_valid_payload_and_redirects_with_success(): void
    {
        $saved = null;
        $this->bindRepository(
            $this->defaultConfig(),
            function (DhlConfiguration $config) use (&$saved) {
                $saved = $config;
            }
        );
        $this->actingAs($this->adminUser());

        $response = $this->put('/admin/settings/dhl-freight', $this->validPayload);

        $response->assertRedirect('/admin/settings/dhl-freight');
        $response->assertSessionHas('success');

        $this->assertInstanceOf(DhlConfiguration::class, $saved);
        $this->assertSame('https://api-sandbox.dhl.com/auth', $saved->authBaseUrl());
        $this->assertSame('client-id-123', $saved->authClientId());
        $this->assertSame('client-secret-456', $saved->authClientSecret());
        $this->assertSame('freight-key-789', $saved->freightApiKey());
        $this->assertSame('freight-secret-abc', $saved->freightApiSecret());
        $this->assertSame('5012345678', $saved->defaultAccountNumber());
        $this->assertSame('DE', $saved->trackingOriginCountryCode());
        $this->assertSame(15, $saved->timeoutSeconds());
        $this->assertTrue($saved->verifySsl());
    }

    public function test_update_rejects_invalid_url_with_german_error_message(): void
    {
        $this->bindRepository($this->defaultConfig());
        $this->actingAs($this->adminUser());

        $payload = $this->validPayload;
        $payload['auth_base_url'] = 'not-a-url';

        $response = $this->from('/admin/settings/dhl-freight')
            ->put('/admin/settings/dhl-freight', $payload);

        $response->assertRedirect('/admin/settings/dhl-freight');
        $response->assertSessionHasErrors(['auth_base_url']);
        $errors = session('errors')->getBag('default')->get('auth_base_url');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('gueltige URL', $errors[0]);
    }

    public function test_update_with_empty_secret_keeps_existing_value(): void
    {
        $saved = null;
        $this->bindRepository(
            $this->defaultConfig(),
            function (DhlConfiguration $config) use (&$saved) {
                $saved = $config;
            }
        );
        $this->actingAs($this->adminUser());

        $payload = $this->validPayload;
        $payload['auth_client_secret'] = '';
        $payload['freight_api_secret'] = '';

        $response = $this->put('/admin/settings/dhl-freight', $payload);

        $response->assertRedirect('/admin/settings/dhl-freight');
        $this->assertInstanceOf(DhlConfiguration::class, $saved);
        $this->assertSame('EXISTING-SECRET-DO-NOT-CHANGE', $saved->authClientSecret());
        $this->assertSame('EXISTING-FREIGHT-SECRET-DO-NOT-CHANGE', $saved->freightApiSecret());
    }

    public function test_test_connection_returns_ok_true_when_gateway_returns_token(): void
    {
        $this->bindRepository($this->defaultConfig());
        $this->app->bind(DhlAuthenticationGateway::class, fn () => new class implements DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                return ['access_token' => 'token-abc', 'token_type' => 'Bearer', 'expires_in' => 3600];
            }
        });
        $this->actingAs($this->adminUser());

        $response = $this->post('/admin/settings/dhl-freight/test-connection');

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_test_connection_returns_ok_false_on_gateway_exception(): void
    {
        $this->bindRepository($this->defaultConfig());
        $this->app->bind(DhlAuthenticationGateway::class, fn () => new class implements DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                throw new RuntimeException('Falsche Credentials.');
            }
        });
        $this->actingAs($this->adminUser());

        $response = $this->post('/admin/settings/dhl-freight/test-connection');

        $response->assertOk();
        $response->assertJson(['ok' => false]);
        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertStringContainsString('Falsche Credentials', (string) ($payload['message'] ?? ''));
    }
}
