<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function test_from_int_returns_identifier(): void
    {
        $identifier = Identifier::fromInt(42);

        self::assertSame(42, $identifier->toInt());
        self::assertSame('42', (string) $identifier);
    }

    public function test_from_int_throws_for_non_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier must be a positive integer.');

        Identifier::fromInt(0);
    }

    public function test_equals_compares_values(): void
    {
        $identifier = Identifier::fromInt(5);
        $same_identifier = Identifier::fromInt(5);
        $different_identifier = Identifier::fromInt(7);

        self::assertTrue($identifier->equals($same_identifier));
        self::assertFalse($identifier->equals($different_identifier));
    }
}
