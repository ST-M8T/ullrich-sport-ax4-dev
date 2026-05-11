<?php

declare(strict_types=1);

namespace App\Application\Fulfillment\Integrations\Dhl\Services;

final class DhlCancellationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $dhlConfirmationNumber,
        public readonly ?string $cancelledAt,
        public readonly ?string $error,
    ) {}
}