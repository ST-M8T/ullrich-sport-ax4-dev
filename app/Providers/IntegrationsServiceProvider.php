<?php

namespace App\Providers;

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

final class IntegrationsServiceProvider extends ServiceProvider
{
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
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

            return new DhlAuthenticationGatewayImpl(
                $http,
                $cache,
                $logger,
                (string) Arr::get($config, 'base_url', ''),
                (string) Arr::get($config, 'username', ''),
                (string) Arr::get($config, 'password', ''),
                is_array($config) ? $config : []
            );
        });

        $this->app->bind(DhlFreightGateway::class, function ($app) {
            $config = $app['config']->get('services.dhl_freight', []);
            $http = $app->make(HttpFactory::class);
            $cache = $app->make(CacheRepository::class);
            $logManager = $app->make(LogManager::class);
            $logger = $logManager->channel(Arr::get($config, 'log_channel', 'stack'));

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
                (string) Arr::get($config, 'base_url', ''),
                (string) Arr::get($config, 'api_key', ''),
                (string) Arr::get($config, 'api_secret', ''),
                is_array($config) ? $config : []
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
