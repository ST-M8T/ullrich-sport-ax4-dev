<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Orders;

/**
 * Immutable pagination response for shipment order listings.
 *
 * @psalm-immutable
 *
 * @phpstan-immutable
 */
final class ShipmentOrderPaginationResult
{
    /**
     * @param  array<int,ShipmentOrder>  $orders
     */
    public function __construct(
        public readonly array $orders,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
    ) {}

    public function totalPages(): int
    {
        return (int) max(1, ceil($this->total / max(1, $this->perPage)));
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->totalPages();
    }
}
