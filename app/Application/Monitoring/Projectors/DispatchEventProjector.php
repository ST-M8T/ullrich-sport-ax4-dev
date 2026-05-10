<?php

declare(strict_types=1);

namespace App\Application\Monitoring\Projectors;

use App\Domain\Monitoring\Contracts\DispatchEventReportRepository;
use App\Domain\Monitoring\DomainEventRecord;
use DateTimeImmutable;
use Exception;

final class DispatchEventProjector
{
    public function __construct(private readonly DispatchEventReportRepository $dispatchEvents) {}

    public function record(DomainEventRecord $record): bool
    {
        $eventId = $record->id()->toString();
        $payload = $record->payload();
        $metadata = $record->metadata();
        $now = $this->now();

        $barcode = $this->extractBarcode($record->eventName(), $payload);
        $occurredAt = $this->resolveDate(
            $this->extractTimestamp($record->eventName(), $payload),
            $record->occurredAt()
        );

        return $this->dispatchEvents->upsert($eventId, [
            'event_name' => $record->eventName(),
            'aggregate_id' => $record->aggregateId(),
            'barcode' => $barcode,
            'occurred_at' => $occurredAt,
            'payload' => $this->encodeJson($payload),
            'metadata' => $this->encodeJson($metadata),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function extractBarcode(string $eventName, array $payload): ?string
    {
        if ($eventName !== 'dispatch.list.scan_captured') {
            return null;
        }

        $barcode = $payload['barcode'] ?? null;

        return is_scalar($barcode) ? trim((string) $barcode) : null;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function extractTimestamp(string $eventName, array $payload): ?string
    {
        return match ($eventName) {
            'dispatch.list.scan_captured' => $payload['captured_at'] ?? null,
            'dispatch.list.metrics_updated' => $payload['calculated_at'] ?? null,
            'dispatch.list.closed' => $payload['closed_at'] ?? null,
            'dispatch.list.exported' => $payload['exported_at'] ?? null,
            default => null,
        };
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
