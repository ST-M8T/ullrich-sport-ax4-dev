<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Configuration;

use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use PHPUnit\Framework\TestCase;

final class DhlConfigurationTest extends TestCase
{
    public function test_create_returns_valid_aggregate_with_defaults(): void
    {
        $config = $this->valid();

        self::assertSame('https://auth.example.com', $config->authBaseUrl());
        self::assertSame('client-id', $config->authClientId());
        self::assertSame('client-secret', $config->authClientSecret());
        self::assertSame('https://api.example.com/freight', $config->freightBaseUrl());
        self::assertSame('api-key', $config->freightApiKey());
        self::assertSame('api-secret', $config->freightApiSecret());
        self::assertNull($config->defaultAccountNumber());
        self::assertSame(10, $config->timeoutSeconds());
        self::assertTrue($config->verifySsl());
    }

    public function test_invalid_url_throws(): void
    {
        $this->expectException(DhlConfigurationException::class);
        DhlConfiguration::create(
            authBaseUrl: 'not-a-url',
            authClientId: 'a',
            authClientSecret: 'b',
            freightBaseUrl: 'https://x',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
    }

    public function test_empty_client_id_throws(): void
    {
        $this->expectException(DhlConfigurationException::class);
        DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: '',
            authClientSecret: 'b',
            freightBaseUrl: 'https://x',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
    }

    public function test_timeout_must_be_positive(): void
    {
        $config = $this->valid();
        $this->expectException(DhlConfigurationException::class);
        $config->setTimeoutSeconds(0);
    }

    public function test_country_code_must_be_iso_alpha2(): void
    {
        $config = $this->valid();
        $this->expectException(DhlConfigurationException::class);
        $config->setTrackingOriginCountryCode('Germany');
    }

    public function test_country_code_accepts_valid_iso(): void
    {
        $config = $this->valid();
        $config->setTrackingOriginCountryCode('DE');
        self::assertSame('DE', $config->trackingOriginCountryCode());
    }

    public function test_default_account_number_normalises_empty_to_null(): void
    {
        $config = $this->valid();
        $config->setDefaultAccountNumber('  ');
        self::assertNull($config->defaultAccountNumber());

        $config->setDefaultAccountNumber('  12345  ');
        self::assertSame('12345', $config->defaultAccountNumber());
    }

    public function test_push_base_url_validates_when_provided(): void
    {
        $config = $this->valid();
        $config->setPushBaseUrl(null);
        self::assertNull($config->pushBaseUrl());

        $this->expectException(DhlConfigurationException::class);
        $config->setPushBaseUrl('not-a-url');
    }

    private function valid(): DhlConfiguration
    {
        return DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: 'client-id',
            authClientSecret: 'client-secret',
            freightBaseUrl: 'https://api.example.com/freight',
            freightApiKey: 'api-key',
            freightApiSecret: 'api-secret',
        );
    }
}
