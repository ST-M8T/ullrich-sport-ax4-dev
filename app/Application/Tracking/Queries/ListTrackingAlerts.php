<?php

namespace App\Application\Tracking\Queries;

use App\Domain\Tracking\Contracts\TrackingAlertRepository;

final class ListTrackingAlerts
{
    public function __construct(private readonly TrackingAlertRepository $alerts) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<\App\Domain\Tracking\TrackingAlert>
     */
    public function __invoke(array $filters = []): iterable
    {
        return $this->alerts->find($filters);
    }
}
