<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Repositories\DhlProductRepository;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @covers \App\Console\Commands\Dhl\Catalog\ListDeprecatedDhlCatalogCommand
 */
final class ListDeprecatedDhlCatalogCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lists_all_deprecated_products_by_default(): void
    {
        $this->seedProduct('NEW', deprecated: false, name: 'A');
        $this->seedProduct('OLD', deprecated: true, successor: 'NEW', name: 'B');
        $this->seedProduct('OBS', deprecated: true, successor: null, name: 'C');

        $this->artisan('dhl:catalog:list-deprecated')
            ->expectsOutputToContain('OLD')
            ->expectsOutputToContain('OBS')
            // Active product NEW does not appear as a row; it appears as the
            // resolved successor reference for OLD.
            ->assertExitCode(0);
    }

    #[Test]
    public function with_successor_flag_filters_to_mapped_products_only(): void
    {
        $this->seedProduct('NEW', deprecated: false);
        $this->seedProduct('OLD', deprecated: true, successor: 'NEW');
        $this->seedProduct('OBS', deprecated: true, successor: null);

        $this->artisan('dhl:catalog:list-deprecated', ['--with-successor' => true])
            ->expectsOutputToContain('OLD')
            ->doesntExpectOutputToContain('OBS')
            ->assertExitCode(0);
    }

    #[Test]
    public function without_successor_flag_filters_to_unmapped_products_only(): void
    {
        $this->seedProduct('NEW', deprecated: false);
        $this->seedProduct('OLD', deprecated: true, successor: 'NEW');
        $this->seedProduct('OBS', deprecated: true, successor: null);

        $this->artisan('dhl:catalog:list-deprecated', ['--without-successor' => true])
            ->expectsOutputToContain('OBS')
            ->doesntExpectOutputToContain(' OLD ')
            ->assertExitCode(0);
    }

    #[Test]
    public function shows_info_message_when_catalog_has_no_deprecated_products(): void
    {
        $this->seedProduct('ACT', deprecated: false);

        $this->artisan('dhl:catalog:list-deprecated')
            ->expectsOutputToContain('No deprecated DHL products')
            ->assertExitCode(0);
    }

    private function seedProduct(
        string $code,
        bool $deprecated,
        ?string $successor = null,
        string $name = 'Product',
    ): void {
        $repo = $this->app->make(DhlProductRepository::class);
        $product = new DhlProduct(
            code: new DhlProductCode($code),
            name: $name,
            description: '',
            marketAvailability: DhlMarketAvailability::B2B,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 1000.0),
            dimensionLimits: new DimensionLimits(240.0, 120.0, 180.0),
            validFrom: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            validUntil: null,
            deprecatedAt: $deprecated ? new DateTimeImmutable('2026-01-01T00:00:00Z') : null,
            replacedByCode: $successor !== null ? new DhlProductCode($successor) : null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
        $repo->save($product, AuditActor::system('test-seed'));
    }
}
