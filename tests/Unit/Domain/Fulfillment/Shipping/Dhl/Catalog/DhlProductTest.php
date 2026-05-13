<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions\InvalidProductSuccessorException;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DhlProductTest extends TestCase
{
    private function buildProduct(
        ?DateTimeImmutable $validFrom = null,
        ?DateTimeImmutable $validUntil = null,
        ?DateTimeImmutable $deprecatedAt = null,
        ?DhlProductCode $replacedByCode = null,
    ): DhlProduct {
        return new DhlProduct(
            code: new DhlProductCode('STD'),
            name: 'Standard',
            description: 'Standard EU Freight',
            marketAvailability: DhlMarketAvailability::BOTH,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT'), new CountryCode('CH')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 1000.0),
            dimensionLimits: new DimensionLimits(240.0, 120.0, 180.0),
            validFrom: $validFrom ?? new DateTimeImmutable('2024-01-01'),
            validUntil: $validUntil,
            deprecatedAt: $deprecatedAt,
            replacedByCode: $replacedByCode,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    public function test_construct_succeeds_with_valid_data(): void
    {
        $p = $this->buildProduct();
        self::assertSame('STD', $p->code()->value);
        self::assertFalse($p->isDeprecated());
    }

    public function test_valid_until_before_valid_from_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildProduct(
            validFrom: new DateTimeImmutable('2025-01-01'),
            validUntil: new DateTimeImmutable('2024-01-01'),
        );
    }

    public function test_valid_until_equal_to_valid_from_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->buildProduct(
            validFrom: new DateTimeImmutable('2025-01-01'),
            validUntil: new DateTimeImmutable('2025-01-01'),
        );
    }

    public function test_replaced_by_self_rejected_in_constructor(): void
    {
        $this->expectException(InvalidProductSuccessorException::class);
        $this->buildProduct(replacedByCode: new DhlProductCode('STD'));
    }

    public function test_deprecate_self_throws(): void
    {
        $p = $this->buildProduct();
        $this->expectException(InvalidProductSuccessorException::class);
        $p->deprecate(new DhlProductCode('STD'), new DateTimeImmutable('2026-01-01'));
    }

    public function test_deprecate_with_successor_works(): void
    {
        $p = $this->buildProduct();
        $p->deprecate(new DhlProductCode('NEW'), new DateTimeImmutable('2026-01-01'));
        self::assertTrue($p->isDeprecated());
        self::assertSame('NEW', $p->replacedByCode()?->value);
    }

    public function test_restore_clears_successor(): void
    {
        $p = $this->buildProduct();
        $p->deprecate(new DhlProductCode('NEW'), new DateTimeImmutable('2026-01-01'));
        $p->restore();
        self::assertFalse($p->isDeprecated());
        self::assertNull($p->replacedByCode());
    }

    public function test_is_valid_at_window(): void
    {
        $p = $this->buildProduct(
            validFrom: new DateTimeImmutable('2024-01-01'),
            validUntil: new DateTimeImmutable('2026-12-31'),
        );

        self::assertFalse($p->isValidAt(new DateTimeImmutable('2023-12-31')));
        self::assertTrue($p->isValidAt(new DateTimeImmutable('2025-06-01')));
        self::assertFalse($p->isValidAt(new DateTimeImmutable('2027-01-01')));
    }

    public function test_is_valid_at_respects_deprecation(): void
    {
        $p = $this->buildProduct();
        $p->deprecate(null, new DateTimeImmutable('2025-06-01'));
        self::assertTrue($p->isValidAt(new DateTimeImmutable('2024-12-01')));
        self::assertFalse($p->isValidAt(new DateTimeImmutable('2025-12-01')));
    }

    public function test_supports_route(): void
    {
        $p = $this->buildProduct();
        self::assertTrue($p->supportsRoute(new CountryCode('DE'), new CountryCode('AT')));
        self::assertFalse($p->supportsRoute(new CountryCode('FR'), new CountryCode('AT')));
        self::assertFalse($p->supportsRoute(new CountryCode('DE'), new CountryCode('FR')));
    }

    public function test_empty_name_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DhlProduct(
            code: new DhlProductCode('STD'),
            name: '',
            description: '',
            marketAvailability: DhlMarketAvailability::BOTH,
            fromCountries: [new CountryCode('DE')],
            toCountries: [new CountryCode('AT')],
            allowedPackageTypes: [new DhlPackageType('PLT')],
            weightLimits: new WeightLimits(0.0, 100.0),
            dimensionLimits: new DimensionLimits(10.0, 10.0, 10.0),
            validFrom: new DateTimeImmutable('2024-01-01'),
            validUntil: null,
            deprecatedAt: null,
            replacedByCode: null,
            source: DhlCatalogSource::SEED,
            syncedAt: null,
        );
    }

    public function test_mark_synced_sets_timestamp(): void
    {
        $p = $this->buildProduct();
        self::assertNull($p->syncedAt());
        $now = new DateTimeImmutable('2026-05-01');
        $p->markSynced($now);
        self::assertSame($now, $p->syncedAt());
    }

    public function test_equals_compares_by_code(): void
    {
        $a = $this->buildProduct();
        $b = $this->buildProduct();
        self::assertTrue($a->equals($b));
    }
}
