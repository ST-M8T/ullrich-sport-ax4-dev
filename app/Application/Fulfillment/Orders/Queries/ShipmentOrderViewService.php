<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Queries;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Fulfillment\Shipments\Shipment;
use App\Domain\Shared\ValueObjects\Identifier;

final class ShipmentOrderViewService
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly ShipmentRepository $shipments,
    ) {}

    public function getOrder(Identifier $id): ?ShipmentOrder
    {
        return $this->orders->getById($id);
    }

    /**
     * @return array{order: ShipmentOrder, shipments: array<int,Shipment>}|null
     */
    public function getOrderWithShipments(Identifier $id): ?array
    {
        $order = $this->orders->getById($id);
        if ($order === null) {
            return null;
        }

        $shipments = [];
        foreach ($order->trackingNumbers() as $trackingNumber) {
            $shipment = $this->shipments->getByTrackingNumber($trackingNumber);
            if ($shipment) {
                $shipments[] = $shipment;
            }
        }

        return [
            'order' => $order,
            'shipments' => $shipments,
        ];
    }
}
