<?php

namespace App\Application\Fulfillment\Shipments\Resources;

use App\Domain\Fulfillment\Shipments\ShipmentEvent;

final class ShipmentEventResource
{
    private function __construct(private readonly ShipmentEvent $event) {}

    public static function fromEvent(ShipmentEvent $event): self
    {
        return new self($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->event->id(),
            'shipment_id' => $this->event->shipmentId(),
            'event_code' => $this->event->eventCode(),
            'status' => $this->event->status(),
            'description' => $this->event->description(),
            'facility' => $this->event->facility(),
            'city' => $this->event->city(),
            'country' => $this->event->country(),
            'occurred_at' => $this->event->occurredAt()->format(DATE_ATOM),
            'payload' => $this->event->payload(),
            'created_at' => $this->event->createdAt()->format(DATE_ATOM),
        ];
    }
}
