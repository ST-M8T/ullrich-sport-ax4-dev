<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Events;

use App\Domain\Fulfillment\Orders\ShipmentOrder;

final class ShipmentOrderTrackingTransferred
{
    /**
     * @param  array<int,string>  $trackingNumbers
     */
    public function __construct(
        public readonly ShipmentOrder $order,
        public readonly array $trackingNumbers,
        public readonly bool $syncImmediately,
    ) {}
}
