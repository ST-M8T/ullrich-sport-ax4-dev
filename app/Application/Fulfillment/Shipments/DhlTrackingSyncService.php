<?php

namespace App\Application\Fulfillment\Shipments;

use App\Domain\Integrations\Contracts\DhlTrackingGateway;
use DateTimeImmutable;

final class DhlTrackingSyncService
{
    public function __construct(
        private readonly DhlTrackingGateway $gateway,
        private readonly ShipmentTrackingService $tracking,
    ) {}

    public function sync(string $trackingNumber): void
    {
        $response = $this->gateway->fetchTrackingEvents($trackingNumber);
        // DHL gibt manchmal `{events: [...]}`, manchmal die Liste direkt zurück.
        $events = $response['events'] ?? $response;
        if (! is_array($events)) {
            $events = [];
        }

        foreach ($events as $event) {
            $occurredAt = new DateTimeImmutable($event['occurredAt'] ?? 'now');
            $this->tracking->recordEvent(
                $trackingNumber,
                $event['code'] ?? null,
                $event['status'] ?? null,
                $event['description'] ?? null,
                $event['facility'] ?? null,
                $event['city'] ?? null,
                $event['country'] ?? null,
                $occurredAt,
                $event
            );
        }
    }
}
