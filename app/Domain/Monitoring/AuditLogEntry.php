<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

use DateTimeImmutable;

final class AuditLogEntry
{
    /**
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        private readonly int $id,
        private readonly string $actorType,
        private readonly ?string $actorId,
        private readonly ?string $actorName,
        private readonly string $action,
        private readonly array $context,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $context
     */
    public static function hydrate(
        int $id,
        string $actorType,
        ?string $actorId,
        ?string $actorName,
        string $action,
        array $context,
        ?string $ipAddress,
        ?string $userAgent,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            trim($actorType),
            $actorId ? trim($actorId) : null,
            $actorName ? trim($actorName) : null,
            trim($action),
            $context,
            $ipAddress ? trim($ipAddress) : null,
            $userAgent ? trim($userAgent) : null,
            $createdAt,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function actorType(): string
    {
        return $this->actorType;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function actorName(): ?string
    {
        return $this->actorName;
    }

    public function action(): string
    {
        return $this->action;
    }

    /**
     * @return array<string,mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
