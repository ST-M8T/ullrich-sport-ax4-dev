<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;

final class SystemJobFailureStreakService
{
    public function __construct(
        private readonly SystemJobRepository $jobs,
    ) {
        // Service is state-free beyond injected repository.
    }

    public function calculate(SystemJobEntry $entry, int $limit = 10): int
    {
        $recent = $this->normalizeIterable($this->jobs->latest($limit, $entry->jobName()));
        $streak = 0;

        foreach ($recent as $job) {
            if (($job instanceof SystemJobEntry) === false) {
                continue;
            }

            if ($job->id() === $entry->id() && strtolower($job->status()) !== 'failed') {
                continue;
            }

            if (strtolower($job->status()) === 'failed') {
                $streak++;

                continue;
            }

            break;
        }

        return $streak;
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<int,T>
     */
    private function normalizeIterable(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
