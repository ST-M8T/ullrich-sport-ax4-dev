<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Stringable;

/**
 * DHL Freight package-type code (spec: pieces[].packageType, max 4 chars,
 * uppercase, alphanumeric — e.g. PLT, COLI, PCS, CRT).
 */
final readonly class DhlPackageType implements Stringable
{
    private const MAX_LENGTH = 4;

    public function __construct(public string $code)
    {
        if ($code === '') {
            throw DhlValueObjectException::invalid('packageType', 'must not be empty', $code);
        }
        if (mb_strlen($code) > self::MAX_LENGTH) {
            throw DhlValueObjectException::invalid('packageType', 'max length 4', $code);
        }
        if ($code !== strtoupper($code)) {
            throw DhlValueObjectException::invalid('packageType', 'must be uppercase', $code);
        }
        if (! ctype_alnum($code)) {
            throw DhlValueObjectException::invalid('packageType', 'must be alphanumeric', $code);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function __toString(): string
    {
        return $this->code;
    }
}
