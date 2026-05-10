<?php

declare(strict_types=1);

namespace App\Domain\Monitoring;

use DateTimeImmutable;
use Ramsey\Uuid\UuidInterface;

final class DomainEventRecord
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly UuidInterface $id,
        private readonly string $eventName,
        private readonly string $aggregateType,
        private readonly string $aggregateId,
        private readonly array $payload,
        private readonly array $metadata,
        private readonly DateTimeImmutable $occurredAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     * @psalm-param array<string,mixed> $metadata
     */
    public static function hydrate(
        UuidInterface $id,
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        array $metadata,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            trim($eventName),
            trim($aggregateType),
            trim($aggregateId),
            $payload,
            $metadata,
            $occurredAt,
            $createdAt,
        );
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
