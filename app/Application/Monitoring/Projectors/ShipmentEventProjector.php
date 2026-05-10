<?php

namespace App\Application\Monitoring\Projectors;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\Contracts\ShipmentEventReportRepository;
use App\Domain\Monitoring\DomainEventRecord;
use DateTimeImmutable;
use Exception;

final class ShipmentEventProjector
{
    public function __construct(
        private readonly TrackingJobService $trackingJobs,
        private readonly ShipmentEventReportRepository $shipmentEvents,
    ) {}

    public function recordShipmentEvent(DomainEventRecord $record): bool
    {
        return $this->store($record, [
            'tracking_number' => $record->payload()['tracking_number'] ?? null,
            'event_code' => $record->payload()['event_code'] ?? null,
            'status' => $record->payload()['status'] ?? null,
            'occurred_at' => $record->payload()['occurred_at'] ?? null,
        ]);
    }

    public function recordManualSync(DomainEventRecord $record): bool
    {
        $payload = $record->payload();

        return $this->store($record, [
            'tracking_number' => $payload['tracking_number'] ?? null,
            'event_code' => $payload['event_code'] ?? 'MANUAL_SYNC',
            'status' => $payload['status'] ?? 'MANUAL_SYNC',
            'occurred_at' => $payload['occurred_at'] ?? $payload['triggered_at'] ?? null,
        ], 'monitor.shipment.manual_sync');
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function store(DomainEventRecord $record, array $overrides, string $monitoringJob = 'monitor.shipment.event'): bool
    {
        $eventId = $record->id()->toString();
        $payload = $record->payload();
        $metadata = $record->metadata();
        $now = $this->now();

        $occurredAt = $this->resolveDate(
            is_string($overrides['occurred_at'] ?? null) ? $overrides['occurred_at'] : null,
            $record->occurredAt()
        );

        $trackingNumber = $overrides['tracking_number'] ?? ($payload['tracking_number'] ?? null);
        $eventCode = $overrides['event_code'] ?? ($payload['event_code'] ?? null);
        $status = $overrides['status'] ?? ($payload['status'] ?? null);
        $carrierCode = $metadata['carrier_code'] ?? null;

        $isNew = $this->shipmentEvents->upsert($eventId, [
            'event_name' => $record->eventName(),
            'aggregate_id' => $record->aggregateId(),
            'tracking_number' => $trackingNumber,
            'event_code' => $eventCode,
            'status' => $status,
            'occurred_at' => $occurredAt,
            'carrier_code' => $carrierCode,
            'payload' => $this->encodeJson($payload),
            'metadata' => $this->encodeJson($metadata),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($isNew) {
            $this->trackingJobs->schedule($monitoringJob, [
                'event_id' => $eventId,
                'shipment_id' => $record->aggregateId(),
                'tracking_number' => $trackingNumber,
                'status' => $status,
                'event_code' => $eventCode,
            ]);
        }

        return $isNew;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }

    private function resolveDate(?string $value, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return $fallback;
        }
    }

    /**
     * @param  array<string,mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
