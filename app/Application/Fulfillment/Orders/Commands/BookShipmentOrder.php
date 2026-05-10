<?php

namespace App\Application\Fulfillment\Orders\Commands;

use App\Application\Fulfillment\Orders\Events\ShipmentOrderBooked;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class BookShipmentOrder
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
    ) {}

    public function __invoke(Identifier $orderId, ?string $bookedBy = null): ShipmentOrder
    {
        $order = $this->orders->getById($orderId);
        if (! $order) {
            throw new \RuntimeException('Shipment order not found.');
        }

        if ($order->isBooked()) {
            return $order;
        }

        $now = new DateTimeImmutable;
        $bookedBy = $bookedBy !== null && $bookedBy !== '' ? trim($bookedBy) : 'admin-panel';

        $updated = ShipmentOrder::hydrate(
            $order->id(),
            $order->externalOrderId(),
            $order->customerNumber(),
            $order->plentyOrderId(),
            $order->orderType(),
            $order->senderProfileId(),
            $order->senderCode(),
            $order->contactEmail(),
            $order->contactPhone(),
            $order->destinationCountry(),
            $order->currency(),
            $order->totalAmount(),
            $order->processedAt(),
            true,
            $now,
            $bookedBy,
            $order->shippedAt(),
            $order->lastExportFilename(),
            $order->items(),
            $order->packages(),
            $order->trackingNumbers(),
            $order->metadata(),
            $order->createdAt(),
            $now,
        );

        $this->orders->save($updated);

        event(new ShipmentOrderBooked($updated, $bookedBy));

        return $updated;
    }
}
