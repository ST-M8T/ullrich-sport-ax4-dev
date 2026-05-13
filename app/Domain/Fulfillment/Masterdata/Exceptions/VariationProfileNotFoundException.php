<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class VariationProfileNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $variationProfileId)
    {
        parent::__construct('Variation profile', $variationProfileId);
    }
}
