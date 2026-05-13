<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Orders\Queries;

/**
 * Immutable criteria object describing a normalised request for the
 * shipment-order index view.
 *
 * The presentation layer transforms the raw HTTP request into this DTO via
 * {@see ShipmentOrderIndexRequestTransformer}. The controller then forwards
 * {@see self::$filters} to {@see ListShipmentOrders} and uses the remaining
 * fields purely for view rendering.
 */
final class ShipmentOrderIndexCriteria
{
    /**
     * @param  array<string,mixed>  $filters  Filter payload consumed by ListShipmentOrders.
     * @param  array<string,mixed>  $viewQuery  Echo data for the view's filter form.
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $filters,
        public readonly array $viewQuery,
        public readonly ?int $expandId,
    ) {
    }
}
