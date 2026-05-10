<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use DateTimeImmutable;

final class SystemSetting
{
    private function __construct(
        private readonly string $key,
        private readonly ?string $value,
        private readonly string $valueType,
        private readonly ?int $updatedByUserId,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    public static function hydrate(
        string $key,
        ?string $value,
        string $valueType,
        ?int $updatedByUserId,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            trim($key),
            $value,
            strtolower(trim($valueType)),
            $updatedByUserId,
            $updatedAt,
        );
    }

    public function key(): string
    {
        return $this->key;
    }

    public function rawValue(): ?string
    {
        return $this->value;
    }

    public function valueType(): string
    {
        return $this->valueType;
    }

    public function isSecret(): bool
    {
        return $this->valueType === 'secret';
    }

    public function updatedByUserId(): ?int
    {
        return $this->updatedByUserId;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
