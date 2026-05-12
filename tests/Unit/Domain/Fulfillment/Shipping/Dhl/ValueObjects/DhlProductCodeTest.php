<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlProductCodeTest extends TestCase
{
    public function test_valid_code_is_accepted(): void
    {
        $code = new DhlProductCode('ECF');
        self::assertSame('ECF', $code->value);
        self::assertSame('ECF', (string) $code);
    }

    public function test_from_string_normalizes(): void
    {
        $code = DhlProductCode::fromString(' ecf ');
        self::assertSame('ECF', $code->value);
    }

    public function test_too_long_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlProductCode('TOOLONG');
    }

    public function test_lowercase_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlProductCode('ecf');
    }

    public function test_empty_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlProductCode('');
    }

    public function test_non_alphanumeric_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlProductCode('E-F');
    }
}
