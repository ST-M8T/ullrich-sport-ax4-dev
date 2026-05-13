<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Thrown when a product is being deprecated in favour of an invalid successor —
 * in particular when the successor code equals the product's own code
 * (circular deprecation).
 */
final class InvalidProductSuccessorException extends DhlCatalogException
{
    public function __construct(public readonly DhlProductCode $productCode)
    {
        parent::__construct(sprintf(
            'Product "%s" cannot be deprecated in favour of itself.',
            $productCode->value,
        ));
    }
}
