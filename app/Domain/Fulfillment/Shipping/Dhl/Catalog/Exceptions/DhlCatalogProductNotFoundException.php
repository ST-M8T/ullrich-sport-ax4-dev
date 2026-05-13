<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Thrown when a referenced DHL product code does not exist in the catalog.
 */
final class DhlCatalogProductNotFoundException extends DhlCatalogException
{
    public function __construct(public readonly DhlProductCode $productCode)
    {
        parent::__construct(sprintf('DHL product "%s" not found in catalog.', $productCode->value));
    }
}
