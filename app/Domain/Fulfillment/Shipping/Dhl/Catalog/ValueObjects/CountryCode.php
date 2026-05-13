<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;
use Stringable;

/**
 * ISO-3166-1 alpha-2 country code (uppercase, exactly 2 alphabetic chars).
 *
 * Intentionally a small VO instead of pulling a heavy locale library — the
 * catalog only needs the syntactic guarantee. The list of *supported* countries
 * is a separate concern (config/dhl-catalog.php).
 */
final readonly class CountryCode implements Stringable
{
    public function __construct(public string $value)
    {
        if (mb_strlen($value) !== 2) {
            throw DhlValueObjectException::invalid('countryCode', 'must be exactly 2 chars', $value);
        }
        if ($value !== strtoupper($value)) {
            throw DhlValueObjectException::invalid('countryCode', 'must be uppercase', $value);
        }
        if (! ctype_alpha($value)) {
            throw DhlValueObjectException::invalid('countryCode', 'must be alphabetic', $value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
