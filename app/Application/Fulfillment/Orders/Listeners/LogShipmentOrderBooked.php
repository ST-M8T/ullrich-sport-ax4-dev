<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Listeners;

use App\Application\Fulfillment\Orders\Events\ShipmentOrderBooked;
use App\Application\Monitoring\AuditLogger;

final class LogShipmentOrderBooked
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ShipmentOrderBooked $event): void
    {
        $order = $event->order;

        $this->auditLogger->log(
            'fulfillment.shipment_order.booked',
            'user',
            null,
            $event->bookedBy,
            [
                'shipment_order_id' => $order->id()->toInt(),
                'external_order_id' => $order->externalOrderId(),
            ],
        );
    }
}
