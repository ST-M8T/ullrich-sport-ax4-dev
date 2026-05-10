<?php

namespace App\Application\Fulfillment\Shipments\Queries;

use App\Domain\Fulfillment\Shipments\Contracts\ShipmentRepository;
use App\Domain\Fulfillment\Shipments\ShipmentPaginationResult;

final class ListShipments
{
    public function __construct(private readonly ShipmentRepository $shipments) {}

    /**
     * @param  array<string,mixed>  $filters
     */
    public function __invoke(int $page = 1, int $perPage = 25, array $filters = []): ShipmentPaginationResult
    {
        return $this->shipments->paginate($page, $perPage, $filters);
    }
}
