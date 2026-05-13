<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Thrown when a successor is being assigned to a product that is not (yet)
 * deprecated. Successor-mapping is only meaningful for deprecated products.
 */
final class DhlCatalogProductNotDeprecatedException extends DhlCatalogException
{
    public function __construct(public readonly DhlProductCode $productCode)
    {
        parent::__construct(sprintf(
            'DHL product "%s" is not deprecated; setting a successor is only allowed for deprecated products.',
            $productCode->value,
        ));
    }
}
