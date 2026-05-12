<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlPayerCode;
use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use PHPUnit\Framework\TestCase;

final class DhlPayerCodeTest extends TestCase
{
    public function test_from_string_accepts_all_allowed_codes(): void
    {
        self::assertSame(DhlPayerCode::DAP, DhlPayerCode::fromString('DAP'));
        self::assertSame(DhlPayerCode::DDP, DhlPayerCode::fromString('ddp'));
        self::assertSame(DhlPayerCode::EXW, DhlPayerCode::fromString(' exw '));
        self::assertSame(DhlPayerCode::CIP, DhlPayerCode::fromString('CIP'));
    }

    public function test_invalid_value_throws(): void
    {
        $this->expectException(DhlValueObjectException::class);
        DhlPayerCode::fromString('FOB');
    }
}
