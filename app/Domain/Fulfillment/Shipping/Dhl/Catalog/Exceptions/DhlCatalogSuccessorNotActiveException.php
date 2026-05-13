<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\DhlProductCode;

/**
 * Thrown when the proposed successor product is itself deprecated and therefore
 * cannot serve as a replacement for another deprecated product.
 */
final class DhlCatalogSuccessorNotActiveException extends DhlCatalogException
{
    public function __construct(public readonly DhlProductCode $productCode)
    {
        parent::__construct(sprintf(
            'DHL product "%s" is deprecated and cannot be used as a successor.',
            $productCode->value,
        ));
    }
}
