<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Origin of a catalog entry.
 *
 * - SEED:   loaded once from a JSON fixture shipped with the codebase
 * - API:    refreshed automatically by the DHL sync job (PROJ-2)
 * - MANUAL: edited by an operator via the admin UI (PROJ-6)
 */
enum DhlCatalogSource: string
{
    case SEED = 'seed';
    case API = 'api';
    case MANUAL = 'manual';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(strtolower(trim($value)));
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'catalogSource',
                rule: 'must be one of seed, api, manual',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
