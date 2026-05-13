<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\Catalog\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * Maximum length / width / height (in centimetres) accepted by a DHL product.
 *
 * Each dimension must be strictly positive. The VO does not enforce an upper
 * bound — that is DHL's call and may evolve. Validation against actual piece
 * dimensions is the caller's responsibility (see PROJ-3 mapping logic).
 */
final readonly class DimensionLimits
{
    public function __construct(
        public float $maxLengthCm,
        public float $maxWidthCm,
        public float $maxHeightCm,
    ) {
        foreach (['maxLengthCm' => $maxLengthCm, 'maxWidthCm' => $maxWidthCm, 'maxHeightCm' => $maxHeightCm] as $field => $value) {
            if ($value <= 0.0) {
                throw DhlValueObjectException::invalid(
                    field: 'dimensionLimits.' . $field,
                    rule: 'must be > 0',
                    rejectedValue: (string) $value,
                );
            }
        }
    }

    public function fits(float $lengthCm, float $widthCm, float $heightCm): bool
    {
        return $lengthCm <= $this->maxLengthCm
            && $widthCm <= $this->maxWidthCm
            && $heightCm <= $this->maxHeightCm;
    }

    public function equals(self $other): bool
    {
        return $this->maxLengthCm === $other->maxLengthCm
            && $this->maxWidthCm === $other->maxWidthCm
            && $this->maxHeightCm === $other->maxHeightCm;
    }
}
