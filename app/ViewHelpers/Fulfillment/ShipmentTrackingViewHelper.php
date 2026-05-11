<?php

declare(strict_types=1);

namespace App\ViewHelpers\Fulfillment;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlEventCodeLabel;
use App\Domain\Fulfillment\Shipments\Shipment;

final class ShipmentTrackingViewHelper
{
    /**
     * Returns shipment data prepared for the order detail view with German labels.
     *
     * @return array<string, mixed>
     */
    public static function toOrderDetailArray(Shipment $shipment): array
    {
        $events = $shipment->events();

        // Reverse to chronological order (oldest first)
        $chronologicalEvents = array_reverse($events);

        $eventsWithLabels = array_map(
            static fn ($event): array => [
                'event_code' => $event->eventCode(),
                'label' => DhlEventCodeLabel::label($event->eventCode() ?? ''),
                'status' => $event->status(),
                'description' => $event->description(),
                'facility' => $event->facility(),
                'city' => $event->city(),
                'country' => $event->country(),
                'occurred_at' => $event->occurredAt()?->format('d.m.Y H:i'),
            ],
            $chronologicalEvents
        );

        $statusCode = $shipment->statusCode();

        return [
            'id' => $shipment->id()->toInt(),
            'tracking_number' => $shipment->trackingNumber(),
            'carrier_code' => $shipment->carrierCode(),
            'current_status' => [
                'code' => $statusCode,
                'label' => DhlEventCodeLabel::label($statusCode ?? ''),
            ],
            'status_description' => $shipment->statusDescription(),
            'is_delivered' => $shipment->isDelivered(),
            'last_event_at' => $shipment->lastEventAt()?->format('d.m.Y H:i'),
            'delivered_at' => $shipment->deliveredAt()?->format('d.m.Y H:i'),
            'events' => $eventsWithLabels,
        ];
    }
}