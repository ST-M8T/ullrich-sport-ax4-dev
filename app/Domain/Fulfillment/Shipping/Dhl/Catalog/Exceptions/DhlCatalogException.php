<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

use DomainException;

/**
 * Base domain exception for the DHL catalog bounded sub-context.
 *
 * Every catalog-specific exception extends this so callers can catch the whole
 * family without depending on `\DomainException` directly. Carries no HTTP
 * code, no framework reference — pure domain (§16 Engineering Handbook).
 */
abstract class DhlCatalogException extends DomainException
{
}
