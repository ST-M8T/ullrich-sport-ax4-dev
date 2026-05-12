<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight party-type discriminator (spec: party.type).
 *
 * Spec values are PascalCase strings.
 */
enum DhlPartyType: string
{
    case Consignor = 'Consignor';
    case Pickup = 'Pickup';
    case Consignee = 'Consignee';
    case Delivery = 'Delivery';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(trim($value));
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'party.type',
                rule: 'must be one of Consignor, Pickup, Consignee, Delivery',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
