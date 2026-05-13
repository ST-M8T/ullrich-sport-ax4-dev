<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class FreightProfileNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $shippingProfileId)
    {
        parent::__construct('Freight profile', $shippingProfileId);
    }
}
