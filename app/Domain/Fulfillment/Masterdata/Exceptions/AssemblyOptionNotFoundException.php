<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Masterdata\Exceptions;

final class AssemblyOptionNotFoundException extends MasterdataNotFoundException
{
    public function __construct(int $assemblyOptionId)
    {
        parent::__construct('Assembly option', $assemblyOptionId);
    }
}
