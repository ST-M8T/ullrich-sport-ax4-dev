<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations\Dhl\Settings;

use App\Application\Fulfillment\Integrations\Dhl\Settings\DhlSettingsResolver;
use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentFreightProfileRepository;
use App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfiguration;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\DhlConfigurationRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Configuration\Exceptions\DhlConfigurationException;
use App\Domain\Shared\ValueObjects\Identifier;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class DhlSettingsResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private DhlConfigurationRepository&Mockery\MockInterface $configRepo;

    private FulfillmentFreightProfileRepository&Mockery\MockInterface $profileRepo;

    private DhlSettingsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configRepo = Mockery::mock(DhlConfigurationRepository::class);
        $this->profileRepo = Mockery::mock(FulfillmentFreightProfileRepository::class);
        $this->resolver = new DhlSettingsResolver($this->configRepo, $this->profileRepo);
    }

    public function test_account_number_uses_profile_when_available(): void
    {
        $profile = FulfillmentFreightProfile::hydrate(
            shippingProfileId: Identifier::fromInt(7),
            label: 'Profile A',
            accountNumber: '99887766',
        );
        $this->profileRepo->shouldReceive('getById')
            ->once()
            ->with(Mockery::on(fn (Identifier $id) => $id->toInt() === 7))
            ->andReturn($profile);

        // configRepo MUST NOT be called when profile resolves the account.
        $this->configRepo->shouldNotReceive('load');

        self::assertSame('99887766', $this->resolver->resolveAccountNumber(7));
    }

    public function test_account_number_falls_back_to_default_when_profile_has_no_account(): void
    {
        $profile = FulfillmentFreightProfile::hydrate(
            shippingProfileId: Identifier::fromInt(7),
            label: 'Profile A',
            accountNumber: null,
        );
        $this->profileRepo->shouldReceive('getById')->once()->andReturn($profile);

        $config = $this->validConfig();
        $config->setDefaultAccountNumber('11223344');
        $this->configRepo->shouldReceive('load')->once()->andReturn($config);

        self::assertSame('11223344', $this->resolver->resolveAccountNumber(7));
    }

    public function test_account_number_uses_default_when_no_profile_id_given(): void
    {
        $config = $this->validConfig();
        $config->setDefaultAccountNumber('SYSDEFAULT');
        $this->configRepo->shouldReceive('load')->once()->andReturn($config);

        self::assertSame('SYSDEFAULT', $this->resolver->resolveAccountNumber(null));
    }

    public function test_account_number_throws_when_neither_set(): void
    {
        $this->profileRepo->shouldReceive('getById')->andReturn(null);
        $config = $this->validConfig(); // no defaultAccountNumber
        $this->configRepo->shouldReceive('load')->once()->andReturn($config);

        $this->expectException(DhlConfigurationException::class);
        $this->resolver->resolveAccountNumber(42);
    }

    public function test_resolve_product_code_returns_profile_value(): void
    {
        $profile = FulfillmentFreightProfile::hydrate(
            shippingProfileId: Identifier::fromInt(3),
            label: 'X',
            dhlProductId: 'V01PAK',
        );
        $this->profileRepo->shouldReceive('getById')->once()->andReturn($profile);

        self::assertSame('V01PAK', $this->resolver->resolveProductCode(3));
    }

    public function test_resolve_default_service_codes_returns_profile_array_or_empty(): void
    {
        $profile = FulfillmentFreightProfile::hydrate(
            shippingProfileId: Identifier::fromInt(3),
            label: 'X',
            dhlDefaultServiceCodes: ['PR', 'IC'],
        );
        $this->profileRepo->shouldReceive('getById')->once()->andReturn($profile);

        self::assertSame(['PR', 'IC'], $this->resolver->resolveDefaultServiceCodes(3));
    }

    public function test_resolve_default_service_codes_returns_empty_when_profile_missing(): void
    {
        $this->profileRepo->shouldReceive('getById')->once()->andReturn(null);
        self::assertSame([], $this->resolver->resolveDefaultServiceCodes(99));
    }

    private function validConfig(): DhlConfiguration
    {
        return DhlConfiguration::create(
            authBaseUrl: 'https://auth.example.com',
            authClientId: 'cid',
            authClientSecret: 'csec',
            freightBaseUrl: 'https://api.example.com',
            freightApiKey: 'k',
            freightApiSecret: 's',
        );
    }
}
