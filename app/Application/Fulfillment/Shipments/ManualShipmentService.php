<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Shipments;

use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Fulfillment\Shipments\Shipment;
use DateTimeImmutable;

final class ManualShipmentService
{
    public function __construct(private readonly ShipmentRepository $shipments) {}

    public function findOrCreate(string $trackingNumber): Shipment
    {
        $normalized = trim($trackingNumber);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Tracking number must not be empty.');
        }

        $existing = $this->shipments->getByTrackingNumber($normalized);
        if ($existing) {
            return $existing;
        }

        $now = new DateTimeImmutable;
        $shipment = Shipment::hydrate(
            $this->shipments->nextIdentity(),
            'MANUAL',
            null,
            $normalized,
            'PENDING',
            'Manuell hinterlegt',
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            [],
            [],
            [],
            $now,
            $now,
        );

        $this->shipments->save($shipment);

        return $shipment;
    }
}
