<?php

namespace App\Providers;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use App\Domain\Integrations\Contracts\DhlPushGateway;
use App\Domain\Integrations\Contracts\DhlTrackingGateway;
use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Infrastructure\Integrations\Dhl\DhlAuthenticationGatewayImpl;
use App\Infrastructure\Integrations\Dhl\DhlFreightGatewayImpl;
use App\Infrastructure\Integrations\Dhl\DhlPushGatewayImpl;
use App\Infrastructure\Integrations\Dhl\DhlTrackingGatewayImpl;
use App\Infrastructure\Integrations\Plenty\PlentyRestGateway;
use App\Support\CircuitBreaker;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class IntegrationsServiceProvider extends ServiceProvider
{
    /**
     * Lädt die DHL-Konfiguration aus der DB (system_settings via Repository).
     * Liefert null, wenn keine Konfiguration vorhanden ist — Aufrufer fällt
     * dann auf config('services.dhl_*') zurück.
     *
     * Engineering-Handbuch §6: ServiceProvider injiziert das Domain-Repository
     * statt eines direkten Config-Lookups. Secrets stammen primär aus der DB
     * (verschlüsselt via SecretEncryptionService, §19), non-secret Felder
     * (paths, timeout, retry, log_channel) bleiben in config/services.php.
     *
     * Fallback auf config() greift, wenn:
     *  - die Pflicht-Settings noch nicht gepflegt sind (DhlConfigurationException)
     *  - die DB nicht erreichbar ist / Tabelle fehlt (z.B. SQLite-in-Memory in Tests)
     */
    private function loadDhlDomainConfig($app): ?DhlConfiguration
    {
        try {
            return $app->make(DhlConfigurationRepository::class)->load();
        } catch (DhlConfigurationException) {
            return null;
        } catch (Throwable) {
            return null;
        }
    }

    public function register(): void
    {
        $this->app->bind(PlentyOrderGateway::class, function ($app) {
            $config = $app['config']->get('services.plenty', []);
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            return new PlentyRestGateway(
                $http,
                new CircuitBreaker(
                    $cache,
                    'plenty.orders',
                    (int) Arr::get($config, 'circuit_breaker.failures', 5),
                    (int) Arr::get($config, 'circuit_breaker.cooldown', 60)
                ),
                $logger,
                (string) Arr::get($config, 'base_url', ''),
                (string) Arr::get($config, 'username', ''),
                (string) Arr::get($config, 'password', ''),
                is_array($config) ? $config : []
            );
        });

        $this->app->bind(DhlTrackingGateway::class, function ($app) {
            $config = $app['config']->get('services.dhl', []);
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            return new DhlTrackingGatewayImpl(
                $http,
                new CircuitBreaker(
                    $cache,
                    'dhl',
                    (int) Arr::get($config, 'circuit_breaker.failures', 5),
                    (int) Arr::get($config, 'circuit_breaker.cooldown', 60)
                ),
                $logger,
                (string) Arr::get($config, 'base_url', ''),
                (string) Arr::get($config, 'api_key', ''),
                is_array($config) ? $config : []
            );
        });

        $this->app->bind(DhlAuthenticationGateway::class, function ($app) {
            $config = $app['config']->get('services.dhl_auth', []);
            $config = is_array($config) ? $config : [];
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            // Secrets primär aus DB (Engineering-Handbuch §6/§19), Fallback auf
            // config() für Tests/Bootstrap. Non-Secret-Felder bleiben in config.
            $domainConfig = $this->loadDhlDomainConfig($app);
            $baseUrl = $domainConfig?->authBaseUrl() ?? (string) Arr::get($config, 'base_url', '');
            $clientId = $domainConfig?->authClientId() ?? (string) Arr::get($config, 'username', '');
            $clientSecret = $domainConfig?->authClientSecret() ?? (string) Arr::get($config, 'password', '');

            return new DhlAuthenticationGatewayImpl(
                $http,
                $cache,
                $logger,
                $baseUrl,
                $clientId,
                $clientSecret,
                $config
            );
        });

        $this->app->bind(DhlFreightGateway::class, function ($app) {
            $config = $app['config']->get('services.dhl_freight', []);
            $config = is_array($config) ? $config : [];
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            $domainConfig = $this->loadDhlDomainConfig($app);
            $baseUrl = $domainConfig?->freightBaseUrl() ?? (string) Arr::get($config, 'base_url', '');
            $apiKey = $domainConfig?->freightApiKey() ?? (string) Arr::get($config, 'api_key', '');
            $apiSecret = $domainConfig?->freightApiSecret() ?? (string) Arr::get($config, 'api_secret', '');

            return new DhlFreightGatewayImpl(
                $http,
                new CircuitBreaker(
                    $cache,
                    'dhl.freight',
                    (int) Arr::get($config, 'circuit_breaker.failures', 5),
                    (int) Arr::get($config, 'circuit_breaker.cooldown', 60)
                ),
                $logger,
                $app->make(DhlAuthenticationGateway::class),
                $baseUrl,
                $apiKey,
                $apiSecret,
                $config
            );
        });

        $this->app->bind(DhlPushGateway::class, function ($app) {
            $config = $app['config']->get('services.dhl_push', []);
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            return new DhlPushGatewayImpl(
                $http,
                new CircuitBreaker(
                    $cache,
                    'dhl.push',
                    (int) Arr::get($config, 'circuit_breaker.failures', 5),
                    (int) Arr::get($config, 'circuit_breaker.cooldown', 60)
                ),
                $logger,
                (string) Arr::get($config, 'base_url', ''),
                (string) Arr::get($config, 'api_key', ''),
                is_array($config) ? $config : []
            );
        });
    }
}
