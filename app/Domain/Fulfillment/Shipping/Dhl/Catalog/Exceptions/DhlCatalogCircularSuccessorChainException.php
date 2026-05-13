<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

/**
 * Thrown when assigning a successor would create a cycle in the
 * `replaced_by_code` chain (e.g. A → B → C → A).
 */
final class DhlCatalogCircularSuccessorChainException extends DhlCatalogException
{
    /**
     * @param  list<string>  $chain  ordered list of product codes describing the cycle
     */
    public function __construct(public readonly array $chain)
    {
        parent::__construct(sprintf(
            'Circular successor chain detected: %s',
            implode(' -> ', $chain),
        ));
    }
}
