<?php

namespace App\Application\Fulfillment\Shipments\Queries;

use App\Application\Fulfillment\Shipments\Resources\ShipmentDetailResource;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;

final class GetShipmentDetail
{
    public function __construct(private readonly ShipmentRepository $shipments) {}

    public function __invoke(string $trackingNumber): ?ShipmentDetailResource
    {
        $shipment = $this->shipments->getByTrackingNumber($trackingNumber);

        if (! $shipment) {
            return null;
        }

        return ShipmentDetailResource::fromShipment($shipment);
    }
}
