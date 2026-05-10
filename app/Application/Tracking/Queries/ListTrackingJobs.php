<?php

declare(strict_types=1);

namespace App\Application\Tracking\Queries;

use App\Domain\Tracking\Contracts\TrackingJobRepository;

final class ListTrackingJobs
{
    public function __construct(
        private readonly TrackingJobRepository $jobs,
    ) {
        // Query object relies on repository injection only.
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<\App\Domain\Tracking\TrackingJob>
     */
    public function __invoke(array $filters = []): iterable
    {
        return $this->jobs->find($filters);
    }
}
