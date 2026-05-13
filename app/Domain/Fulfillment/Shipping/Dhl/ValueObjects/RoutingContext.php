<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Routing dimension under which a DHL booking, price-quote or service
 * validation is executed.
 *
 * All three fields are nullable to express a *global* lookup (no specific
 * routing axis). The constructor enforces the syntactic ISO-3166-1 alpha-2
 * format on the country codes — semantic validation against
 * config('dhl-catalog.default_countries') happens in the catalog layer.
 *
 * Engineering-Handbuch §3-§8: lives in Domain (no framework imports). §67:
 * fail-fast on malformed input at construction time.
 */
final readonly class RoutingContext
{
    public function __construct(
        private ?string $fromCountry,
        private ?string $toCountry,
        private ?DhlPayerCode $payerCode,
    ) {
        if ($fromCountry !== null) {
            self::assertIsoCountry('fromCountry', $fromCountry);
        }
        if ($toCountry !== null) {
            self::assertIsoCountry('toCountry', $toCountry);
        }
    }

    public static function global(): self
    {
        return new self(null, null, null);
    }

    /**
     * Build a RoutingContext for a concrete shipment. Country codes are
     * normalized to uppercase to satisfy the ISO-3166-1 alpha-2 invariant.
     * Any axis may be null when the caller cannot determine it (e.g. order
     * without destination country) — the catalog lookup will then degrade
     * gracefully.
     */
    public static function forShipment(
        ?string $fromCountry,
        ?string $toCountry,
        ?DhlPayerCode $payerCode,
    ): self {
        return new self(
            $fromCountry !== null && $fromCountry !== '' ? strtoupper(trim($fromCountry)) : null,
            $toCountry !== null && $toCountry !== '' ? strtoupper(trim($toCountry)) : null,
            $payerCode,
        );
    }

    public function fromCountry(): ?string
    {
        return $this->fromCountry;
    }

    public function toCountry(): ?string
    {
        return $this->toCountry;
    }

    public function payerCode(): ?DhlPayerCode
    {
        return $this->payerCode;
    }

    public function equals(self $other): bool
    {
        return $this->fromCountry === $other->fromCountry
            && $this->toCountry === $other->toCountry
            && $this->payerCode === $other->payerCode;
    }

    private static function assertIsoCountry(string $field, string $value): void
    {
        if (mb_strlen($value) !== 2) {
            throw DhlValueObjectException::invalid($field, 'must be exactly 2 chars', $value);
        }
        if ($value !== strtoupper($value)) {
            throw DhlValueObjectException::invalid($field, 'must be uppercase', $value);
        }
        if (! ctype_alpha($value)) {
            throw DhlValueObjectException::invalid($field, 'must be alphabetic', $value);
        }
    }
}
