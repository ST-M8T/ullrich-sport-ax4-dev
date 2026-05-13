<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\Catalog;

use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\AuditActor;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\CountryCode;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlCatalogSource;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlMarketAvailability;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceCategory;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DhlServiceRequirement;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\DimensionLimits;
use App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects\WeightLimits;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_country_code_accepts_iso2(): void
    {
        $c = new CountryCode('DE');
        self::assertSame('DE', $c->value);
        self::assertSame('DE', (string) $c);
        self::assertTrue($c->equals(CountryCode::fromString(' de ')));
    }

    public function test_country_code_rejects_wrong_length(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new CountryCode('DEU');
    }

    public function test_country_code_rejects_lowercase(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new CountryCode('de');
    }

    public function test_country_code_rejects_non_alpha(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new CountryCode('D1');
    }

    public function test_weight_limits_construct_ok(): void
    {
        $w = new WeightLimits(0.0, 100.0);
        self::assertTrue($w->contains(50.0));
        self::assertFalse($w->contains(150.0));
    }

    public function test_weight_limits_negative_min_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new WeightLimits(-1.0, 10.0);
    }

    public function test_weight_limits_max_le_min_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new WeightLimits(10.0, 10.0);
    }

    public function test_dimension_limits_construct_ok(): void
    {
        $d = new DimensionLimits(100.0, 50.0, 50.0);
        self::assertTrue($d->fits(80.0, 40.0, 40.0));
        self::assertFalse($d->fits(120.0, 40.0, 40.0));
    }

    public function test_dimension_limits_zero_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DimensionLimits(0.0, 10.0, 10.0);
    }

    public function test_enums_from_string(): void
    {
        self::assertSame(DhlServiceCategory::PICKUP, DhlServiceCategory::fromString('PICKUP'));
        self::assertSame(DhlServiceRequirement::REQUIRED, DhlServiceRequirement::fromString('Required'));
        self::assertSame(DhlCatalogSource::API, DhlCatalogSource::fromString('api'));
        self::assertSame(DhlMarketAvailability::B2B, DhlMarketAvailability::fromString('b2b'));
    }

    public function test_enum_invalid_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        DhlServiceCategory::fromString('nonsense');
    }

    public function test_audit_actor_system(): void
    {
        $a = AuditActor::system('dhl-sync');
        self::assertSame('system:dhl-sync', (string) $a);
    }

    public function test_audit_actor_user(): void
    {
        $a = AuditActor::user(42);
        self::assertSame('user:42', (string) $a);
    }

    public function test_audit_actor_invalid_prefix_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new AuditActor('admin:42');
    }

    public function test_audit_actor_empty_suffix_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new AuditActor('system:');
    }
}
