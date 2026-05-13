<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class PackagingProfileNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $packagingProfileId)
    {
        parent::__construct('Packaging profile', $packagingProfileId);
    }
}
