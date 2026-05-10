<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Shipments\Listeners;

use App\Application\Fulfillment\Shipments\Events\ShipmentManualSyncTriggered;
use App\Application\Monitoring\AuditLogger;
use App\Application\Monitoring\DomainEventService;

final class RecordManualSync
{
    public function __construct(
        private readonly DomainEventService $events,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(ShipmentManualSyncTriggered $event): void
    {
        $shipment = $event->shipment;
        $payload = $event->event;

        $this->events->record(
            'fulfillment.shipment.manual_sync_triggered',
            'shipment',
            (string) $shipment->id()->toInt(),
            [
                'tracking_number' => $shipment->trackingNumber(),
                'event_code' => $payload->eventCode(),
                'initiator' => $event->initiator,
                'occurred_at' => $payload->occurredAt()->format(DATE_ATOM),
            ],
            [
                'carrier_code' => $shipment->carrierCode(),
            ],
        );

        $this->auditLogger->log(
            'shipment.manual_sync_triggered',
            'admin',
            $event->initiator,
            $event->initiator,
            [
                'shipment_id' => $shipment->id()->toInt(),
                'tracking_number' => $shipment->trackingNumber(),
                'note' => $event->note,
            ],
        );
    }
}
