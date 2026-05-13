<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class SenderProfileNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $senderProfileId)
    {
        parent::__construct('Sender profile', $senderProfileId);
    }
}
