<?php

namespace App\Application\Monitoring\Projectors;

use App\Domain\Monitoring\Contracts\NotificationEventReportRepository;
use App\Domain\Monitoring\DomainEventRecord;
use DateTimeImmutable;
use Exception;

final class NotificationEventProjector
{
    public function __construct(private readonly NotificationEventReportRepository $notificationEvents) {}

    public function record(DomainEventRecord $record): bool
    {
        $eventId = $record->id()->toString();
        $payload = $record->payload();
        $metadata = $record->metadata();
        $now = $this->now();

        $channel = $this->toString($payload['channel'] ?? null);
        $notificationType = $this->toString($payload['notification_type'] ?? null);
        $recipient = $this->toString($payload['recipient'] ?? null);
        $sentAt = $this->resolveDate($payload['sent_at'] ?? null, $record->occurredAt());
        $template = $this->toString($metadata['template'] ?? null);

        return $this->notificationEvents->upsert($eventId, [
            'event_name' => $record->eventName(),
            'aggregate_id' => $record->aggregateId(),
            'channel' => $channel,
            'notification_type' => $notificationType,
            'recipient' => $recipient,
            'sent_at' => $sentAt,
            'template' => $template,
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
