<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight Incoterm-based payer code (spec: payerCode).
 *
 * Allowed values per business decision: DAP, DDP, EXW, CIP. No default — the
 * caller must always pick one.
 */
enum DhlPayerCode: string
{
    case DAP = 'DAP';
    case DDP = 'DDP';
    case EXW = 'EXW';
    case CIP = 'CIP';

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        $candidate = self::tryFrom($normalized);
        if ($candidate === null) {
            throw DhlValueObjectException::invalid(
                field: 'payerCode',
                rule: 'must be one of DAP, DDP, EXW, CIP',
                rejectedValue: $value,
            );
        }

        return $candidate;
    }
}
