<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\Exceptions;

/**
 * Thrown when a parameter set fails validation against a JsonSchema.
 *
 * Carries the JSON-pointer path of the offending field and a plain-text reason
 * so callers (sync job, mapper) can produce precise diagnostics without
 * re-parsing the message.
 */
final class InvalidParameterException extends DhlCatalogException
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
    ) {
        parent::__construct(sprintf('Invalid catalog parameter at "%s": %s', $path, $reason));
    }
}
