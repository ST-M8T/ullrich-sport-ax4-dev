<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\RoutingContext;
use PHPUnit\Framework\TestCase;

final class RoutingContextTest extends TestCase
{
    public function test_constructor_accepts_full_routing(): void
    {
        $r = new RoutingContext('DE', 'AT', DhlPayerCode::DAP);

        self::assertSame('DE', $r->fromCountry());
        self::assertSame('AT', $r->toCountry());
        self::assertSame(DhlPayerCode::DAP, $r->payerCode());
    }

    public function test_global_factory_returns_all_nulls(): void
    {
        $r = RoutingContext::global();

        self::assertNull($r->fromCountry());
        self::assertNull($r->toCountry());
        self::assertNull($r->payerCode());
    }

    public function test_rejects_non_iso2_from_country(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new RoutingContext('DEU', 'AT', null);
    }

    public function test_rejects_lowercase_from_country(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new RoutingContext('de', 'AT', null);
    }

    public function test_rejects_non_alpha_to_country(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new RoutingContext('DE', 'A1', null);
    }

    public function test_equals_returns_true_for_same_values(): void
    {
        $a = new RoutingContext('DE', 'AT', DhlPayerCode::DAP);
        $b = new RoutingContext('DE', 'AT', DhlPayerCode::DAP);

        self::assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_routing(): void
    {
        $a = new RoutingContext('DE', 'AT', DhlPayerCode::DAP);
        $b = new RoutingContext('DE', 'CH', DhlPayerCode::DAP);
        $c = new RoutingContext('DE', 'AT', DhlPayerCode::DDP);

        self::assertFalse($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function test_equals_global_to_global(): void
    {
        self::assertTrue(RoutingContext::global()->equals(RoutingContext::global()));
    }
}
