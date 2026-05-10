<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Events;

use App\Domain\Fulfillment\Orders\ShipmentOrder;

final class ShipmentOrderBooked
{
    public function __construct(
        public readonly ShipmentOrder $order,
        public readonly ?string $bookedBy,
    ) {}
}
