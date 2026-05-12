<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight reference-qualifier (spec: references[].qualifier).
 *
 *  - CNR: customer reference number   (mapped from external order id)
 *  - CNZ: customer number             (mapped from internal customer number)
 *  - INV: invoice number
 */
enum DhlReferenceQualifier: string
{
    case CNR = 'CNR';
    case CNZ = 'CNZ';
    case INV = 'INV';

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        $candidate = self::tryFrom($normalized);
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'reference.qualifier',
                rule: 'must be one of CNR, CNZ, INV',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
