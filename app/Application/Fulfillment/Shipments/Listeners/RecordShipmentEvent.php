<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Shipments\Listeners;

use App\Application\Fulfillment\Shipments\Events\ShipmentEventRecorded;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;

final class RecordShipmentEvent
{
    public function __construct(
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(ShipmentEventRecorded $event): void
    {
        $shipment = $event->shipment;
        $payload = $event->event;

        $this->events->record(
            'fulfillment.shipment.event_recorded',
            'shipment',
            (string) $shipment->id()->toInt(),
            [
                'tracking_number' => $shipment->trackingNumber(),
                'event_code' => $payload->eventCode(),
                'status' => $payload->status(),
                'occurred_at' => $payload->occurredAt()->format(DATE_ATOM),
            ],
            [
                'carrier_code' => $shipment->carrierCode(),
            ],
        );

        $this->auditLogger->log(
            'shipment.event_recorded',
            'system',
            null,
            null,
            [
                'shipment_id' => $shipment->id()->toInt(),
                'tracking_number' => $shipment->trackingNumber(),
                'event_code' => $payload->eventCode(),
                'status' => $payload->status(),
            ],
        );
    }
}
