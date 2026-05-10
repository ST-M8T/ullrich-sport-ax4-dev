<?php

namespace App\Application\Fulfillment\Shipments;

use App\Application\Fulfillment\Shipments\Events\ShipmentEventRecorded;
use App\Application\Fulfillment\Shipments\Events\ShipmentManualSyncTriggered;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Fulfillment\Shipments\Shipment;
use App\Domain\Fulfillment\Shipments\ShipmentEvent;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;

final class ShipmentTrackingService
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
    ) {}

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public function recordEvent(
        string $trackingNumber,
        ?string $eventCode,
        ?string $status,
        ?string $description,
        ?string $facility,
        ?string $city,
        ?string $country,
        DateTimeImmutable $occurredAt,
        array $payload
    ): Shipment {
        $shipment = $this->shipments->getByTrackingNumber($trackingNumber);
        if (! $shipment) {
            throw new \RuntimeException('Shipment not found for tracking '.$trackingNumber);
        }

        $eventId = $this->shipments->nextEventIdentity($shipment->id());
        $event = ShipmentEvent::hydrate(
            $eventId,
            $shipment->id()->toInt(),
            $eventCode,
            $status,
            $description,
            $facility,
            $city,
            $country,
            $occurredAt,
            $payload,
            new DateTimeImmutable,
        );

        $updated = $shipment->applyEvent($event, $payload);

        $this->shipments->save($updated);
        $this->shipments->appendEvent($shipment->id(), $event);

        Event::dispatch(new ShipmentEventRecorded($updated, $event));

        return $updated;
    }

    public function triggerManualSync(int $shipmentId, string $initiator, ?string $note = null): Shipment
    {
        $shipment = $this->shipments->getById(Identifier::fromInt($shipmentId));
        if (! $shipment) {
            throw new \RuntimeException('Shipment not found for id '.$shipmentId);
        }

        $occurredAt = new DateTimeImmutable;
        $eventId = $this->shipments->nextEventIdentity($shipment->id());

        $description = $note !== null && trim($note) !== ''
            ? trim($note)
            : 'Manueller Sync ausgelöst';

        $payload = [
            'initiator' => $initiator,
            'note' => $description,
            'type' => 'manual_sync',
            'triggered_at' => $occurredAt->format(DATE_ATOM),
        ];

        $event = ShipmentEvent::hydrate(
            $eventId,
            $shipment->id()->toInt(),
            'MANUAL_SYNC',
            'MANUAL_SYNC',
            $description,
            null,
            null,
            null,
            $occurredAt,
            $payload,
            $occurredAt,
        );

        $updated = $shipment->applyEvent($event, $payload);

        $this->shipments->save($updated);
        $this->shipments->appendEvent($shipment->id(), $event);

        Event::dispatch(new ShipmentManualSyncTriggered($updated, $event, $initiator, $description));

        return $updated;
    }
}
