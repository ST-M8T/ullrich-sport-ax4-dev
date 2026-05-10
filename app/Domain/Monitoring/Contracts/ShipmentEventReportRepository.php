<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

interface ShipmentEventReportRepository
{
    /**
     * @param  array<string,mixed>  $attributes
     */
    public function upsert(string $eventId, array $attributes): bool;
}
