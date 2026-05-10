<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use DateTimeImmutable;

final class SystemSecretVersion
{
    public function __construct(
        private readonly int $id,
        private readonly string $settingKey,
        private readonly int $version,
        private readonly ?string $encryptedValue,
        private readonly ?int $rotatedByUserId,
        private readonly DateTimeImmutable $rotatedAt,
        private readonly ?DateTimeImmutable $deactivatedAt,
    ) {}

    public static function create(
        string $settingKey,
        int $version,
        ?string $encryptedValue,
        ?int $rotatedByUserId,
        DateTimeImmutable $rotatedAt,
    ): self {
        return new self(
            0,
            trim($settingKey),
            $version,
            $encryptedValue,
            $rotatedByUserId,
            $rotatedAt,
            null,
        );
    }

    public static function hydrate(
        int $id,
        string $settingKey,
        int $version,
        ?string $encryptedValue,
        ?int $rotatedByUserId,
        DateTimeImmutable $rotatedAt,
        ?DateTimeImmutable $deactivatedAt,
    ): self {
        return new self(
            $id,
            trim($settingKey),
            $version,
            $encryptedValue,
            $rotatedByUserId,
            $rotatedAt,
            $deactivatedAt,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function settingKey(): string
    {
        return $this->settingKey;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function encryptedValue(): ?string
    {
        return $this->encryptedValue;
    }

    public function rotatedByUserId(): ?int
    {
        return $this->rotatedByUserId;
    }

    public function rotatedAt(): DateTimeImmutable
    {
        return $this->rotatedAt;
    }

    public function deactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function isActive(): bool
    {
        return $this->deactivatedAt === null;
    }
}
