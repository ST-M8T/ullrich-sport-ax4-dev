<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final class Identifier
{
    /**
     * Sentinel value used by {@see Identifier::placeholder()} for newly-created
     * aggregates that have not yet been persisted (the DB will assign the real id).
     */
    private const PLACEHOLDER_VALUE = 0;

    private function __construct(private readonly int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Identifier must not be negative.');
        }
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw new InvalidArgumentException('Identifier must be a positive integer.');
        }

        return new self($value);
    }

    /**
     * Returns a sentinel identifier for an aggregate that has not yet been
     * persisted. The repository must detect placeholders via {@see isPlaceholder()}
     * and let the underlying storage assign the real id.
     */
    public static function placeholder(): self
    {
        return new self(self::PLACEHOLDER_VALUE);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function isPlaceholder(): bool
    {
        return $this->value === self::PLACEHOLDER_VALUE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
