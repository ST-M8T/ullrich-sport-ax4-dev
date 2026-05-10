<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipments;

use DateTimeImmutable;

final class ShipmentEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        private readonly int $id,
        private readonly int $shipmentId,
        private readonly ?string $eventCode,
        private readonly ?string $status,
        private readonly ?string $description,
        private readonly ?string $facility,
        private readonly ?string $city,
        private readonly ?string $country,
        private readonly DateTimeImmutable $occurredAt,
        private readonly array $payload,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public static function hydrate(
        int $id,
        int $shipmentId,
        ?string $eventCode,
        ?string $status,
        ?string $description,
        ?string $facility,
        ?string $city,
        ?string $country,
        DateTimeImmutable $occurredAt,
        array $payload,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $shipmentId,
            $eventCode ? trim($eventCode) : null,
            $status ? trim($status) : null,
            $description ? trim($description) : null,
            $facility ? trim($facility) : null,
            $city ? trim($city) : null,
            $country ? strtoupper(trim($country)) : null,
            $occurredAt,
            $payload,
            $createdAt,
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function shipmentId(): int
    {
        return $this->shipmentId;
    }

    public function eventCode(): ?string
    {
        return $this->eventCode;
    }

    public function status(): ?string
    {
        return $this->status;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function facility(): ?string
    {
        return $this->facility;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
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
