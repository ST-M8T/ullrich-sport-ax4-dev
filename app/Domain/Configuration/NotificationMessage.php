<?php

declare(strict_types=1);

namespace App\Domain\Configuration;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly string $notificationType,
        private readonly ?string $channel,
        private readonly array $payload,
        private readonly string $status,
        private readonly ?DateTimeImmutable $scheduledAt,
        private readonly ?DateTimeImmutable $sentAt,
        private readonly ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public static function hydrate(
        Identifier $id,
        string $notificationType,
        ?string $channel,
        array $payload,
        string $status,
        ?DateTimeImmutable $scheduledAt,
        ?DateTimeImmutable $sentAt,
        ?string $errorMessage,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            trim($notificationType),
            $channel ? trim($channel) : null,
            $payload,
            trim($status),
            $scheduledAt,
            $sentAt,
            $errorMessage ? trim($errorMessage) : null,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function notificationType(): string
    {
        return $this->notificationType;
    }

    public function channel(): ?string
    {
        return $this->channel;
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function scheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
