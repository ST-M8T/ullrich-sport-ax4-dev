<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Contracts;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingAlert;

interface TrackingAlertRepository
{
    public function nextIdentity(): Identifier;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<TrackingAlert>
     */
    public function find(array $filters = []): iterable;

    public function getById(Identifier $id): ?TrackingAlert;

    public function save(TrackingAlert $alert): void;
}
