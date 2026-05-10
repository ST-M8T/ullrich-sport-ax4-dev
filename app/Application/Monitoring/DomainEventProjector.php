<?php

namespace App\Application\Monitoring;

use App\Application\Monitoring\Projectors\DispatchEventProjector;
use App\Application\Monitoring\Projectors\NotificationEventProjector;
use App\Application\Monitoring\Projectors\OrderEventProjector;
use App\Application\Monitoring\Projectors\ShipmentEventProjector;
use App\Domain\Monitoring\DomainEventRecord;
use App\Jobs\DispatchDomainEventFollowUp;
use Illuminate\Support\Facades\Log;

final class DomainEventProjector
{
    public function __construct(
        private readonly ShipmentEventProjector $shipmentProjector,
        private readonly DispatchEventProjector $dispatchProjector,
        private readonly OrderEventProjector $orderProjector,
        private readonly NotificationEventProjector $notificationProjector,
    ) {}

    public function project(DomainEventRecord $record): void
    {
        $shouldDispatchFollowUp = match ($record->eventName()) {
            'fulfillment.shipment.event_recorded' => $this->shipmentProjector->recordShipmentEvent($record),
            'fulfillment.shipment.manual_sync_triggered' => $this->shipmentProjector->recordManualSync($record),
            'dispatch.list.scan_captured',
            'dispatch.list.metrics_updated',
            'dispatch.list.closed',
            'dispatch.list.exported' => $this->dispatchProjector->record($record),
            'fulfillment.shipment_order.synced' => $this->orderProjector->record($record),
            'configuration.notification.sent' => $this->notificationProjector->record($record),
            default => $this->logUnhandled($record),
        };

        if ($shouldDispatchFollowUp) {
            DispatchDomainEventFollowUp::dispatch(
                $record->id()->toString(),
                $record->eventName(),
                $record->aggregateType(),
                $record->aggregateId(),
                $record->payload(),
                $record->metadata()
            );
        }
    }

    private function logUnhandled(DomainEventRecord $record): bool
    {
        Log::debug('Domain event projector: no handler registered', [
            'event' => $record->eventName(),
            'aggregate_type' => $record->aggregateType(),
        ]);

        return false;
    }
}
