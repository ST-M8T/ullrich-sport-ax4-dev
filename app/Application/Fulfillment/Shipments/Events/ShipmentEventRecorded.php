<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Shipments\Events;

use App\Domain\Fulfillment\Shipments\Shipment;
use App\Domain\Fulfillment\Shipments\ShipmentEvent;

final class ShipmentEventRecorded
{
    public function __construct(
        public readonly Shipment $shipment,
        public readonly ShipmentEvent $event,
    ) {}
}
