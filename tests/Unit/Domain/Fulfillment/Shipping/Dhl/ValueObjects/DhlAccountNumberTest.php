<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlAccountNumber;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlAccountNumberTest extends TestCase
{
    public function test_valid_account_number_is_accepted(): void
    {
        $acc = new DhlAccountNumber('123456789012345');
        self::assertSame('123456789012345', $acc->value);
        self::assertSame('123456789012345', (string) $acc);
    }

    public function test_empty_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAccountNumber('');
    }

    public function test_too_long_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        new DhlAccountNumber('1234567890123456');
    }
}
