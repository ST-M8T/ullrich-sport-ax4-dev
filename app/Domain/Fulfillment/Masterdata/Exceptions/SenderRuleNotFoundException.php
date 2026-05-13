<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class SenderRuleNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $senderRuleId)
    {
        parent::__construct('Sender rule', $senderRuleId);
    }
}
