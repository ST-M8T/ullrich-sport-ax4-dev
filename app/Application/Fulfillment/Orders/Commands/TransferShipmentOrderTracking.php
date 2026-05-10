<?php

namespace App\Application\Fulfillment\Orders\Commands;

use App\Application\Fulfillment\Orders\Events\ShipmentOrderTrackingTransferred;
use App\Application\Fulfillment\Shipments\ShipmentTrackingService;
use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

final class TransferShipmentOrderTracking
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly ShipmentTrackingService $tracking,
    ) {}

    /**
     * @return array<int,string>
     */
    public function __invoke(
        Identifier $orderId,
        ?string $trackingNumber = null,
        bool $syncImmediately = false
    ): array {
        $order = $this->orders->getById($orderId);
        if (! $order) {
            throw new \RuntimeException('Shipment order not found.');
        }

        $numbers = $trackingNumber !== null && $trackingNumber !== ''
            ? [$trackingNumber]
            : $order->trackingNumbers();

        $numbers = array_values(array_filter(array_map('trim', $numbers)));
        if ($numbers === []) {
            throw new \RuntimeException('No tracking numbers available for transfer.');
        }

        $processed = [];
        $occurredAt = new DateTimeImmutable;

        foreach ($numbers as $number) {
            try {
                $this->tracking->recordEvent(
                    $number,
                    'TRANSFER',
                    $syncImmediately ? 'TRANSFER_SYNC_NOW' : 'TRANSFER_REQUESTED',
                    'Tracking transfer triggered via admin panel',
                    null,
                    null,
                    null,
                    $occurredAt,
                    [
                        'source' => 'admin-panel',
                        'shipment_order_id' => $order->id()->toInt(),
                        'external_order_id' => $order->externalOrderId(),
                        'sync_immediately' => $syncImmediately,
                    ]
                );
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    sprintf('Transfer failed for tracking %s: %s', $number, $exception->getMessage()),
                    (int) $exception->getCode(),
                    $exception
                );
            }

            $processed[] = $number;
        }

        event(new ShipmentOrderTrackingTransferred($order, $processed, $syncImmediately));

        return $processed;
    }
}
