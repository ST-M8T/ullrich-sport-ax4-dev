<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Fulfillment\Shipping\Dhl;

use App\Application\Configuration\SystemSettingService;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use App\Infrastructure\Persistence\Eloquent\Fulfillment\Shipping\Dhl\EloquentDhlConfigurationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentDhlConfigurationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentDhlConfigurationRepository $repository;

    private SystemSettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = $this->app->make(SystemSettingService::class);
        $this->repository = new EloquentDhlConfigurationRepository($this->settings);
    }

    public function test_implements_interface(): void
    {
        self::assertInstanceOf(DhlConfigurationRepository::class, $this->repository);
    }

    public function test_load_throws_when_required_keys_missing(): void
    {
        $this->expectException(DhlConfigurationException::class);
        $this->repository->load();
    }

    public function test_save_then_load_roundtrip_preserves_values(): void
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.dhl.example/oauth',
            authClientId: 'my-client',
            authClientSecret: 'my-secret',
            freightBaseUrl: 'https://freight.dhl.example/api',
            freightApiKey: 'fk-123',
            freightApiSecret: 'fs-456',
        );
        $config->setDefaultAccountNumber('12345678');
        $config->setTrackingApiKey('track-key');
        $config->setTrackingDefaultService('parcel');
        $config->setTrackingOriginCountryCode('DE');
        $config->setTrackingRequesterCountryCode('AT');
        $config->setTimeoutSeconds(20);
        $config->setVerifySsl(false);
        $config->setPushBaseUrl('https://push.dhl.example');
        $config->setPushApiKey('push-key');

        $this->repository->save($config);

        $reloaded = $this->repository->load();

        self::assertSame('https://auth.dhl.example/oauth', $reloaded->authBaseUrl());
        self::assertSame('my-client', $reloaded->authClientId());
        self::assertSame('my-secret', $reloaded->authClientSecret());
        self::assertSame('https://freight.dhl.example/api', $reloaded->freightBaseUrl());
        self::assertSame('fk-123', $reloaded->freightApiKey());
        self::assertSame('fs-456', $reloaded->freightApiSecret());
        self::assertSame('12345678', $reloaded->defaultAccountNumber());
        self::assertSame('track-key', $reloaded->trackingApiKey());
        self::assertSame('parcel', $reloaded->trackingDefaultService());
        self::assertSame('DE', $reloaded->trackingOriginCountryCode());
        self::assertSame('AT', $reloaded->trackingRequesterCountryCode());
        self::assertSame(20, $reloaded->timeoutSeconds());
        self::assertFalse($reloaded->verifySsl());
        self::assertSame('https://push.dhl.example', $reloaded->pushBaseUrl());
        self::assertSame('push-key', $reloaded->pushApiKey());
    }

    public function test_save_writes_to_canonical_system_setting_keys(): void
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.dhl.example',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.dhl.example',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
        $config->setDefaultAccountNumber('99887766');

        $this->repository->save($config);

        self::assertSame('https://auth.dhl.example', $this->settings->get('dhl_auth_base_url'));
        self::assertSame('cid', $this->settings->get('dhl_auth_username'));
        self::assertSame('csec', $this->settings->get('dhl_auth_password'));
        self::assertSame('https://api.dhl.example', $this->settings->get('dhl_freight_base_url'));
        self::assertSame('k', $this->settings->get('dhl_freight_api_key'));
        self::assertSame('s', $this->settings->get('dhl_freight_api_secret'));
        self::assertSame('99887766', $this->settings->get('dhl_default_account_number'));
    }

    public function test_save_is_idempotent(): void
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.dhl.example',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.dhl.example',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );

        $this->repository->save($config);
        $this->repository->save($config); // no exception, no duplicate

        $reloaded = $this->repository->load();
        self::assertSame('cid', $reloaded->authClientId());
    }

    public function test_save_removes_optional_value_when_set_to_null(): void
    {
        $config = DhlConfiguration::create(
            authBaseUrl: 'https://auth.dhl.example',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.dhl.example',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
        $config->setDefaultAccountNumber('1');
        $this->repository->save($config);
        self::assertSame('1', $this->settings->get('dhl_default_account_number'));

        $config->setDefaultAccountNumber(null);
        $this->repository->save($config);
        self::assertNull($this->settings->get('dhl_default_account_number'));
    }
}
