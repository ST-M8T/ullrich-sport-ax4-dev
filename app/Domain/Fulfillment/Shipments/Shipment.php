<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipments;

use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class Shipment
{
    /**
     * @param  array<int,ShipmentEvent>  $events
     * @param  array<string, mixed>  $lastPayload
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly Identifier $id,
        private readonly string $carrierCode,
        private readonly ?int $shippingProfileId,
        private readonly string $trackingNumber,
        private readonly ?string $statusCode,
        private readonly ?string $statusDescription,
        private readonly ?DateTimeImmutable $lastEventAt,
        private readonly ?DateTimeImmutable $deliveredAt,
        private readonly ?DateTimeImmutable $nextSyncAfter,
        private readonly ?float $weightKg,
        private readonly ?float $volumeDm3,
        private readonly ?int $piecesCount,
        private readonly int $failedAttempts,
        private readonly array $lastPayload,
        private readonly array $metadata,
        private readonly array $events,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param  array<int,ShipmentEvent>  $events
     *
     * @psalm-param array<string,mixed> $lastPayload
     * @psalm-param array<string,mixed> $metadata
     */
    public static function hydrate(
        Identifier $id,
        string $carrierCode,
        ?int $shippingProfileId,
        string $trackingNumber,
        ?string $statusCode,
        ?string $statusDescription,
        ?DateTimeImmutable $lastEventAt,
        ?DateTimeImmutable $deliveredAt,
        ?DateTimeImmutable $nextSyncAfter,
        ?float $weightKg,
        ?float $volumeDm3,
        ?int $piecesCount,
        int $failedAttempts,
        array $lastPayload,
        array $metadata,
        array $events,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            trim($carrierCode),
            $shippingProfileId,
            trim($trackingNumber),
            $statusCode ? trim($statusCode) : null,
            $statusDescription ? trim($statusDescription) : null,
            $lastEventAt,
            $deliveredAt,
            $nextSyncAfter,
            $weightKg !== null ? max(0.0, $weightKg) : null,
            $volumeDm3 !== null ? max(0.0, $volumeDm3) : null,
            $piecesCount !== null ? max(0, $piecesCount) : null,
            max(0, $failedAttempts),
            $lastPayload,
            $metadata,
            $events,
            $createdAt,
            $updatedAt,
        );
    }

    public function id(): Identifier
    {
        return $this->id;
    }

    public function carrierCode(): string
    {
        return $this->carrierCode;
    }

    public function shippingProfileId(): ?int
    {
        return $this->shippingProfileId;
    }

    public function trackingNumber(): string
    {
        return $this->trackingNumber;
    }

    public function statusCode(): ?string
    {
        return $this->statusCode;
    }

    public function statusDescription(): ?string
    {
        return $this->statusDescription;
    }

    public function lastEventAt(): ?DateTimeImmutable
    {
        return $this->lastEventAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function nextSyncAfter(): ?DateTimeImmutable
    {
        return $this->nextSyncAfter;
    }

    public function weightKg(): ?float
    {
        return $this->weightKg;
    }

    public function volumeDm3(): ?float
    {
        return $this->volumeDm3;
    }

    public function piecesCount(): ?int
    {
        return $this->piecesCount;
    }

    public function failedAttempts(): int
    {
        return $this->failedAttempts;
    }

    /**
     * @return array<string,mixed>
     */
    public function lastPayload(): array
    {
        return $this->lastPayload;
    }

    /**
     * @return array<string,mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array<int,ShipmentEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public function applyEvent(ShipmentEvent $event, array $payload): self
    {
        $updated = self::hydrate(
            $this->id,
            $this->carrierCode,
            $this->shippingProfileId,
            $this->trackingNumber,
            $event->status() ?? $this->statusCode,
            $event->description() ?? $this->statusDescription,
            $event->occurredAt(),
            $this->deliveredAt,
            $this->nextSyncAfter,
            $this->weightKg,
            $this->volumeDm3,
            $this->piecesCount,
            $this->failedAttempts,
            $payload,
            $this->metadata,
            array_merge([$event], $this->events),
            $this->createdAt,
            new DateTimeImmutable,
        );

        if ($event->status() && str_starts_with($event->status(), 'DELIVER')) {
            $updated = self::hydrate(
                $updated->id(),
                $updated->carrierCode(),
                $updated->shippingProfileId(),
                $updated->trackingNumber(),
                $updated->statusCode(),
                $updated->statusDescription(),
                $updated->lastEventAt(),
                $event->occurredAt(),
                $updated->nextSyncAfter(),
                $updated->weightKg(),
                $updated->volumeDm3(),
                $updated->piecesCount(),
                $updated->failedAttempts(),
                $updated->lastPayload(),
                $updated->metadata(),
                $updated->events(),
                $updated->createdAt(),
                new DateTimeImmutable,
            );
        }

        return $updated;
    }
}
