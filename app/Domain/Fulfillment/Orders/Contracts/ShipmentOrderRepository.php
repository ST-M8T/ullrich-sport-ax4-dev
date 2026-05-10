<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders\Contracts;

use App\Domain\Fulfillment\Orders\ShipmentOrder;
use App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult;
use App\Domain\Shared\ValueObjects\Identifier;

interface ShipmentOrderRepository
{
    /**
     * @param  array<string,mixed>  $filters  Supported keys:
     *                                        - filter: recent|booked|unbooked
     *                                        - sender_code: string
     *                                        - destination_country: string (ISO2)
     *                                        - processed_from: \DateTimeInterface
     *                                        - processed_to: \DateTimeInterface
     *                                        - search: string
     *                                        - sort: processed_at|order_id|kunden_id|email|country|rechbetrag|booked_at|tracking_number
     *                                        - direction: asc|desc
     *                                        - is_booked: bool
     */
    public function paginate(int $page, int $perPage, array $filters = []): ShipmentOrderPaginationResult;

    public function getById(Identifier $id): ?ShipmentOrder;

    public function getByExternalOrderId(int $externalOrderId): ?ShipmentOrder;

    public function nextIdentity(): Identifier;

    public function save(ShipmentOrder $order): void;

    public function linkShipment(Identifier $orderId, Identifier $shipmentId): void;
}
