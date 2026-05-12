<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReferenceQualifier;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlReferenceQualifierTest extends TestCase
{
    public function test_from_string_accepts_all_qualifiers(): void
    {
        self::assertSame(DhlReferenceQualifier::CNR, DhlReferenceQualifier::fromString('cnr'));
        self::assertSame(DhlReferenceQualifier::CNZ, DhlReferenceQualifier::fromString('CNZ'));
        self::assertSame(DhlReferenceQualifier::INV, DhlReferenceQualifier::fromString(' INV '));
    }

    public function test_invalid_qualifier_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        DhlReferenceQualifier::fromString('PO');
    }
}
