<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Packaging;

use App\Domain\Fulfillment\Orders\Contracts\ShipmentOrderRepository;
use App\Domain\Shared\ValueObjects\Identifier;
use RuntimeException;

/**
 * Use case: recompute the package list of an existing ShipmentOrder from
 * the configured masterdata (FulfillmentVariationProfile + FulfillmentPackagingProfile)
 * and persist the result.
 *
 * The presentation layer must NOT touch the repository directly
 * (Engineering-Handbuch §7/§70). This service is the one orchestration
 * boundary for the recalculate-flow.
 */
class RecalculateOrderPackages
{
    public function __construct(
        private readonly ShipmentOrderRepository $orders,
        private readonly OrderPackageCalculator $calculator,
    ) {}

    /**
     * @return int Number of packages produced (0 = no masterdata match).
     */
    public function execute(Identifier $orderId): int
    {
        $order = $this->orders->getById($orderId);
        if ($order === null) {
            throw new RuntimeException('Shipment order not found.');
        }

        $packages = $this->calculator->calculate($order);
        if ($packages === []) {
            return 0;
        }

        $this->orders->save($order->withPackages($packages));

        return count($packages);
    }
}
