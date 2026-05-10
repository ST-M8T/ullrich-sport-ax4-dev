<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects\Pagination;

use InvalidArgumentException;

/**
 * Value Object representing a single page with number and size.
 * Immutable.
 */
final class Page
{
    public const MIN_SIZE = 1;
    public const MAX_SIZE = 200;
    public const DEFAULT_SIZE = 25;

    private function __construct(
        private readonly int $number,
        private readonly int $size,
    ) {
        if ($number < 1) {
            throw new InvalidArgumentException('Page number must be at least 1.');
        }
        if ($size < self::MIN_SIZE || $size > self::MAX_SIZE) {
            throw new InvalidArgumentException(sprintf('Page size must be between %d and %d.', self::MIN_SIZE, self::MAX_SIZE));
        }
    }

    public static function create(int $number, int $size): self
    {
        return new self($number, $size);
    }

    public static function first(int $size = self::DEFAULT_SIZE): self
    {
        return new self(1, $size);
    }

    public function number(): int
    {
        return $this->number;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function offset(): int
    {
        return ($this->number - 1) * $this->size;
    }

    public function isFirst(): bool
    {
        return $this->number === 1;
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number && $this->size === $other->size;
    }
}
