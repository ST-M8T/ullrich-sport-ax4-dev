<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPartyType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlPartyTypeTest extends TestCase
{
    public function test_from_string_accepts_all_pascal_case_values(): void
    {
        self::assertSame(DhlPartyType::Consignor, DhlPartyType::fromString('Consignor'));
        self::assertSame(DhlPartyType::Pickup, DhlPartyType::fromString('Pickup'));
        self::assertSame(DhlPartyType::Consignee, DhlPartyType::fromString('Consignee'));
        self::assertSame(DhlPartyType::Delivery, DhlPartyType::fromString('Delivery'));
    }

    public function test_lowercase_is_rejected(): void
    {
        $this->expectException(DhlValueObjectException::class);
        DhlPartyType::fromString('consignor');
    }

    public function test_unknown_value_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        DhlPartyType::fromString('Sender');
    }
}
