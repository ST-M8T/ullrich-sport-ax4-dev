<?php

namespace App\Application\Monitoring\Projectors;

use App\Domain\Monitoring\Contracts\OrderEventReportRepository;
use App\Domain\Monitoring\DomainEventRecord;
use DateTimeImmutable;
use Exception;

final class OrderEventProjector
{
    public function __construct(private readonly OrderEventReportRepository $orderEvents) {}

    public function record(DomainEventRecord $record): bool
    {
        $eventId = $record->id()->toString();
        $payload = $record->payload();
        $metadata = $record->metadata();
        $now = $this->now();

        $externalOrderId = $this->toString($payload['external_order_id'] ?? null);
        $status = $this->toString($payload['status'] ?? null);
        $isUpdate = (bool) ($payload['is_update'] ?? false);
        $syncedAt = $this->resolveDate($payload['synced_at'] ?? null, $record->occurredAt());
        $currency = $this->toString($metadata['currency'] ?? null);
        $totalAmount = $this->toFloat($metadata['total_amount'] ?? null);

        return $this->orderEvents->upsert($eventId, [
            'event_name' => $record->eventName(),
            'aggregate_id' => $record->aggregateId(),
            'external_order_id' => $externalOrderId,
            'status' => $status,
            'is_update' => $isUpdate,
            'synced_at' => $syncedAt,
            'currency' => $currency,
            'total_amount' => $totalAmount,
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

    private function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string !== '' ? $string : null;
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
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
