<?php

namespace App\Application\Tracking;

use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use DateInterval;
use DateTimeImmutable;

class TrackingJobScheduler
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $recurringDefinitions;

    /**
     * @param  array<int|string,array<string,mixed>>  $recurringDefinitions
     */
    public function __construct(
        private readonly TrackingJobRepository $repository,
        private readonly TrackingJobService $jobs,
        array $recurringDefinitions = []
    ) {
        $this->recurringDefinitions = $this->normalizeDefinitions($recurringDefinitions);
    }

    /**
     * @param  callable(TrackingJob):void  $callback
     * @return int number of jobs dispatched
     */
    public function dispatchDueJobs(callable $callback, int $limit = 25, ?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable;

        if ($this->recurringDefinitions !== []) {
            $this->scheduleRecurringJobs($now);
        }

        $jobs = $this->repository->findDueJobs($now, $limit);

        $count = 0;
        foreach ($jobs as $job) {
            $callback($job);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int|string,array<string,mixed>>  $definitions
     * @return array<string,array<string,mixed>>
     */
    private function normalizeDefinitions(array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $jobType = $definition['job_type'] ?? $definition['type'] ?? (is_string($key) ? $key : null);
            if (! is_string($jobType)) {
                continue;
            }

            $jobType = trim($jobType);
            if ($jobType === '') {
                continue;
            }

            $interval = $this->resolveInterval($definition['frequency'] ?? $definition['every'] ?? $definition['interval'] ?? null, 'PT15M');
            if ($interval->invert === 1 || $this->isZeroInterval($interval)) {
                continue;
            }

            $payload = [];
            if (isset($definition['payload']) && is_array($definition['payload'])) {
                $payload = $definition['payload'];
            }

            $normalized[$jobType] = [
                'job_type' => $jobType,
                'payload' => $payload,
                'frequency_interval' => $interval,
                'raw' => $definition,
            ];
        }

        return $normalized;
    }

    private function scheduleRecurringJobs(DateTimeImmutable $now): void
    {
        foreach ($this->recurringDefinitions as $jobType => $definition) {
            $interval = $definition['frequency_interval'];
            if (! $interval instanceof DateInterval || $this->isZeroInterval($interval) || $interval->invert === 1) {
                continue;
            }

            $lastJob = $this->repository->findLatestForType($jobType);
            $lastScheduledAt = $this->resolveReferenceTime($lastJob);
            $payload = $definition['payload'];

            if ($lastScheduledAt === null) {
                $this->jobs->schedule($jobType, $payload, $now);

                continue;
            }

            $next = $lastScheduledAt->add($interval);
            $loopGuard = 0;
            while ($next <= $now) {
                $this->jobs->schedule($jobType, $payload, $next);
                $lastScheduledAt = $next;
                $next = $lastScheduledAt->add($interval);
                $loopGuard++;

                if ($loopGuard > 100) {
                    break;
                }
            }
        }
    }

    private function resolveReferenceTime(?TrackingJob $job): ?DateTimeImmutable
    {
        if ($job === null) {
            return null;
        }

        return $job->scheduledAt()
            ?? $job->startedAt()
            ?? $job->finishedAt()
            ?? $job->createdAt();
    }

    private function resolveInterval(mixed $value, string $default): DateInterval
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateInterval(trim($value));
            } catch (\Exception) {
                // noop, fall back to defaults
            }
        }

        if (is_numeric($value)) {
            $seconds = (int) $value;
            if ($seconds > 0) {
                $interval = DateInterval::createFromDateString($seconds.' seconds');
                if ($interval instanceof DateInterval) {
                    return $interval;
                }
            }
        }

        return new DateInterval($default);
    }

    private function isZeroInterval(DateInterval $interval): bool
    {
        return $interval->y === 0
            && $interval->m === 0
            && $interval->d === 0
            && $interval->h === 0
            && $interval->i === 0
            && $interval->s === 0
            && (int) $interval->f === 0;
    }
}
