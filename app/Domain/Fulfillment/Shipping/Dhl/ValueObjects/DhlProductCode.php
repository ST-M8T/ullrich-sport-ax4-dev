<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Stringable;

/**
 * DHL Freight product code (spec: productCode, max 3 chars, uppercase).
 */
final readonly class DhlProductCode implements Stringable
{
    private const MAX_LENGTH = 3;

    public function __construct(public string $value)
    {
        if ($value === '') {
            throw DhlValueObjectException::invalid('productCode', 'must not be empty', $value);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw DhlValueObjectException::invalid('productCode', 'max length 3', $value);
        }
        if ($value !== strtoupper($value)) {
            throw DhlValueObjectException::invalid('productCode', 'must be uppercase', $value);
        }
        if (! ctype_alnum($value)) {
            throw DhlValueObjectException::invalid('productCode', 'must be alphanumeric', $value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
