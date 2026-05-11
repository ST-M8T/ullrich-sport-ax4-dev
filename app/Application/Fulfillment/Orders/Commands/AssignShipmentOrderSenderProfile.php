<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Commands;

use App\Domain\Fulfillment\Masterdata\Contracts\FulfillmentSenderProfileRepository;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use RuntimeException;

final class AssignShipmentOrderSenderProfile
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly FulfillmentSenderProfileRepository $senderProfiles,
    ) {}

    public function __invoke(Identifier $orderId, Identifier $senderProfileId): void
    {
        $order = $this->orders->getById($orderId);
        if ($order === null) {
            throw new RuntimeException('Shipment order not found.');
        }

        $senderProfile = $this->senderProfiles->getById($senderProfileId);
        if ($senderProfile === null) {
            throw new RuntimeException('Sender profile not found.');
        }

        $this->orders->save(
            $order->assignSenderProfile($senderProfile->id(), $senderProfile->senderCode())
        );
    }
}
