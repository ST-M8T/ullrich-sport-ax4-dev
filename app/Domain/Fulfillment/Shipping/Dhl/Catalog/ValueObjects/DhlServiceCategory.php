<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * High-level grouping for a DHL additional service.
 *
 * Drives presentation grouping (PROJ-6 UI) and lets the sync job tag inbound
 * services without leaking DHL-internal taxonomy strings into the rest of the
 * code base.
 */
enum DhlServiceCategory: string
{
    case PICKUP = 'pickup';
    case DELIVERY = 'delivery';
    case NOTIFICATION = 'notification';
    case DANGEROUS_GOODS = 'dangerous_goods';
    case SPECIAL = 'special';

    public static function fromString(string $value): self
    {
        $candidate = self::tryFrom(strtolower(trim($value)));
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'serviceCategory',
                rule: 'must be one of pickup, delivery, notification, dangerous_goods, special',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
