<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Shipping\Dhl\ValueObjects;

use App\Domain\Fulfillment\Shipping\Dhl\ValueObjects\Exceptions\DhlValueObjectException;

/**
 * DHL Freight reference entry (spec: references[]).
 *
 * Value length 1..35. Truncation is the mapper's responsibility — this VO
 * fails fast on violations.
 */
final readonly class DhlReference
{
    private const MAX_VALUE = 35;

    public function __construct(
        public DhlReferenceQualifier $qualifier,
        public string $value,
    ) {
        if ($value === '') {
            throw DhlValueObjectException::invalid('reference.value', 'must not be empty', $value);
        }
        if (mb_strlen($value) > self::MAX_VALUE) {
            throw DhlValueObjectException::invalid('reference.value', 'max length 35', $value);
        }
    }

    /**
     * @return array{qualifier:string,value:string}
     */
    public function toArray(): array
    {
        return [
            'qualifier' => $this->qualifier->value,
            'value' => $this->value,
        ];
    }
}
