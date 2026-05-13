<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Whether a particular additional service is allowed, required or forbidden
 * for a given product / routing / payer combination.
 *
 * FORBIDDEN is an explicit override: when a global ALLOWED assignment exists
 * but a specific routing has FORBIDDEN, the specific one wins (see
 * DhlProductServiceAssignment::specificity()).
 */
enum DhlServiceRequirement: string
{
    case ALLOWED = 'allowed';
    case REQUIRED = 'required';
    case FORBIDDEN = 'forbidden';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(strtolower(trim($value)));
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'serviceRequirement',
                rule: 'must be one of allowed, required, forbidden',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
