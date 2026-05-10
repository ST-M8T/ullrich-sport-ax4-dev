<?php

namespace App\Application\Fulfillment\Shipments\Resources;

use App\Domain\Fulfillment\Shipments\Shipment;

final class ShipmentDetailResource
{
    private function __construct(private readonly Shipment $shipment) {}

    public static function fromShipment(Shipment $shipment): self
    {
        return new self($shipment);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->shipment->id()->toInt(),
            'carrier_code' => $this->shipment->carrierCode(),
            'shipping_profile_id' => $this->shipment->shippingProfileId(),
            'tracking_number' => $this->shipment->trackingNumber(),
            'status_code' => $this->shipment->statusCode(),
            'status_description' => $this->shipment->statusDescription(),
            'last_event_at' => $this->shipment->lastEventAt()?->format(DATE_ATOM),
            'delivered_at' => $this->shipment->deliveredAt()?->format(DATE_ATOM),
            'next_sync_after' => $this->shipment->nextSyncAfter()?->format(DATE_ATOM),
            'weight_kg' => $this->shipment->weightKg(),
            'volume_dm3' => $this->shipment->volumeDm3(),
            'pieces_count' => $this->shipment->piecesCount(),
            'failed_attempts' => $this->shipment->failedAttempts(),
            'last_payload' => $this->shipment->lastPayload(),
            'metadata' => $this->shipment->metadata(),
            'events' => array_map(
                static fn ($event) => ShipmentEventResource::fromEvent($event)->toArray(),
                $this->shipment->events()
            ),
            'created_at' => $this->shipment->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->shipment->updatedAt()->format(DATE_ATOM),
            'is_delivered' => $this->shipment->isDelivered(),
        ];
    }
}
