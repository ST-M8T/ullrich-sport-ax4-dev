<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use InvalidArgumentException;

final class PasswordHash
{
    private function __construct(private readonly string $hash)
    {
        if (trim($hash) === '') {
            throw new InvalidArgumentException('Password hash must not be empty.');
        }
    }

    public static function fromString(string $hash): self
    {
        return new self($hash);
    }

    public function toString(): string
    {
        return $this->hash;
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hash, $other->hash);
    }
}
