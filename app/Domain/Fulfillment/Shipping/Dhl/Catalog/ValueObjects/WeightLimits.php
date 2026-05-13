<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Inclusive min / exclusive-upper-or-max weight envelope (in kilograms) for a
 * DHL product. Validated at construction; immutable afterwards.
 */
final readonly class WeightLimits
{
    public function __construct(
        public float $minKg,
        public float $maxKg,
    ) {
        if ($minKg < 0.0) {
            throw DhlValueObjectException::invalid('weightLimits.min', 'must be >= 0', (string) $minKg);
        }
        if ($maxKg <= $minKg) {
            throw DhlValueObjectException::invalid(
                field: 'weightLimits.max',
                rule: 'must be greater than min',
                rejectedValue: sprintf('min=%.3f max=%.3f', $minKg, $maxKg),
            );
        }
    }

    public function contains(float $weightKg): bool
    {
        return $weightKg >= $this->minKg && $weightKg <= $this->maxKg;
    }

    public function equals(self $other): bool
    {
        return $this->minKg === $other->minKg && $this->maxKg === $other->maxKg;
    }
}
