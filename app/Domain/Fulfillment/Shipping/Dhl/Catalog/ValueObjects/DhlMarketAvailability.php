<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Market segment for which a DHL product is offered (B2B, B2C or BOTH).
 */
enum DhlMarketAvailability: string
{
    case B2B = 'B2B';
    case B2C = 'B2C';
    case BOTH = 'BOTH';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(strtoupper(trim($value)));
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'marketAvailability',
                rule: 'must be one of B2B, B2C, BOTH',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
