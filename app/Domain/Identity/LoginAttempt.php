<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class LoginAttempt
{
    private function __construct(
        private readonly Identifier $id,
        private readonly string $username,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly bool $success,
        private readonly ?string $failureReason,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function hydrate(
        Identifier $id,
        string $username,
        ?string $ipAddress,
        ?string $userAgent,
        bool $success,
        ?string $failureReason,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            strtolower(trim($username)),
            $ipAddress ? trim($ipAddress) : null,
            $userAgent ? trim($userAgent) : null,
            $success,
            $failureReason ? trim($failureReason) : null,
            $createdAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
