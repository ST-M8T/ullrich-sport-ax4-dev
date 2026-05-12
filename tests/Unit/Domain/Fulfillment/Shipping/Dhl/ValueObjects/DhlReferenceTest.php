<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReference;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlReferenceQualifier;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlReferenceTest extends TestCase
{
    public function test_valid_reference_to_array_matches_spec_keys(): void
    {
        $ref = new DhlReference(DhlReferenceQualifier::CNR, 'ORDER-12345');
        self::assertSame(['qualifier' => 'CNR', 'value' => 'ORDER-12345'], $ref->toArray());
    }

    public function test_empty_value_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlReference(DhlReferenceQualifier::CNR, '');
    }

    public function test_too_long_value_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlReference(DhlReferenceQualifier::INV, str_repeat('x', 36));
    }
}
