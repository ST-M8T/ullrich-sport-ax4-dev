<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPackageType;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlPackageTypeTest extends TestCase
{
    public function test_valid_code_is_accepted(): void
    {
        $type = new DhlPackageType('PLT');
        self::assertSame('PLT', $type->code);
        self::assertSame('PLT', (string) $type);
    }

    public function test_from_string_normalizes(): void
    {
        $type = DhlPackageType::fromString('coli');
        self::assertSame('COLI', $type->code);
    }

    public function test_too_long_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPackageType('TOOMUCH');
    }

    public function test_empty_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPackageType('');
    }

    public function test_non_alphanumeric_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlPackageType('P-T');
    }
}
