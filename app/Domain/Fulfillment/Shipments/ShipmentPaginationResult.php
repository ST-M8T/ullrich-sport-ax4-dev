<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipments;

/**
 * Immutable pagination response for shipment listings.
 *
 * @psalm-immutable
 *
 * @phpstan-immutable
 */
final class ShipmentPaginationResult
{
    /**
     * @param  array<int,Shipment>  $shipments
     */
    public function __construct(
        public readonly array $shipments,
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
