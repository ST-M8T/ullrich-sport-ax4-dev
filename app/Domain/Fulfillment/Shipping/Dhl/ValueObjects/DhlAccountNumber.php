<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Stringable;

/**
 * DHL Freight account number (spec: party.id / payerAccountNumber, max 15 chars).
 */
final readonly class DhlAccountNumber implements Stringable
{
    private const MAX_LENGTH = 15;

    public function __construct(public string $value)
    {
        if ($value === '') {
            throw DhlValueObjectException::invalid('accountNumber', 'must not be empty', $value);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw DhlValueObjectException::invalid('accountNumber', 'max length 15', $value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
