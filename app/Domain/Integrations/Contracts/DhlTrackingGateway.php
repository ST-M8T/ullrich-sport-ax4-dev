<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

interface DhlTrackingGateway
{
    /**
     * @return array<string,mixed>
     */
    public function fetchTrackingEvents(string $trackingNumber): array;

    /**
     * @return array{status:int,duration_ms:float,body:mixed}
     */
    public function ping(): array;
}
