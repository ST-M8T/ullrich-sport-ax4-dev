<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Listeners;

use App\Application\Fulfillment\Orders\Events\ShipmentOrderTrackingTransferred;
use App\Application\Monitoring\AuditLogger;

final class LogShipmentOrderTrackingTransferred
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(ShipmentOrderTrackingTransferred $event): void
    {
        $order = $event->order;

        $this->auditLogger->log(
            'fulfillment.shipment_order.transfer_tracking',
            'user',
            null,
            null,
            [
                'shipment_order_id' => $order->id()->toInt(),
                'external_order_id' => $order->externalOrderId(),
                'tracking_numbers' => $event->trackingNumbers,
                'sync_immediately' => $event->syncImmediately,
            ],
        );
    }
}
