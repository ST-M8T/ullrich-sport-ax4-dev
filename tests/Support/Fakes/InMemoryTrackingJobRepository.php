<?php

namespace Tests\Support\Fakes;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;

final class InMemoryTrackingJobRepository implements TrackingJobRepository
{
    /**
     * @var array<int,TrackingJob>
     */
    private array $jobs = [];

    public function nextIdentity(): Identifier
    {
        $next = empty($this->jobs) ? 1 : (max(array_keys($this->jobs)) + 1);

        return Identifier::fromInt($next);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,TrackingJob>
     */
    public function find(array $filters = []): iterable
    {
        $filtered = array_filter(
            $this->jobs,
            function (TrackingJob $job) use ($filters) {
                if (isset($filters['job_type']) && $job->jobType() !== $filters['job_type']) {
                    return false;
                }

                if (isset($filters['status']) && $job->status() !== $filters['status']) {
                    return false;
                }

                return true;
            }
        );

        usort(
            $filtered,
            function (TrackingJob $left, TrackingJob $right): int {
                $leftTimestamp = $this->jobSortTimestamp($left);
                $rightTimestamp = $this->jobSortTimestamp($right);

                if ($leftTimestamp === $rightTimestamp) {
                    return $right->id()->toInt() <=> $left->id()->toInt();
                }

                return $rightTimestamp <=> $leftTimestamp;
            }
        );

        return array_values($filtered);
    }

    public function findDueJobs(DateTimeImmutable $cutoff, int $limit = 50): iterable
    {
        $due = array_filter(
            $this->jobs,
            function (TrackingJob $job) use ($cutoff) {
                if ($job->status() !== TrackingJob::STATUS_SCHEDULED) {
                    return false;
                }

                $scheduledAt = $job->scheduledAt();
                if ($scheduledAt === null) {
                    return true;
                }

                return $scheduledAt <= $cutoff;
            }
        );

        usort(
            $due,
            function (TrackingJob $left, TrackingJob $right): int {
                $leftTimestamp = $this->jobSortTimestamp($left);
                $rightTimestamp = $this->jobSortTimestamp($right);

                if ($leftTimestamp === $rightTimestamp) {
                    return $left->id()->toInt() <=> $right->id()->toInt();
                }

                return $leftTimestamp <=> $rightTimestamp;
            }
        );

        $now = new DateTimeImmutable;
        $reserved = [];

        foreach (array_slice($due, 0, $limit) as $job) {
            $reservedJob = $job->reserve($now);
            $this->jobs[$reservedJob->id()->toInt()] = $reservedJob;
            $reserved[] = $reservedJob;
        }

        return $reserved;
    }

    public function getById(Identifier $id): ?TrackingJob
    {
        return $this->jobs[$id->toInt()] ?? null;
    }

    public function save(TrackingJob $job): void
    {
        $this->jobs[$job->id()->toInt()] = $job;
    }

    public function findLatestForType(string $jobType): ?TrackingJob
    {
        $candidates = array_filter(
            $this->jobs,
            fn (TrackingJob $job) => $job->jobType() === $jobType
        );

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            function (TrackingJob $left, TrackingJob $right): int {
                $leftTimestamp = $this->jobSortTimestamp($left);
                $rightTimestamp = $this->jobSortTimestamp($right);

                if ($leftTimestamp === $rightTimestamp) {
                    return $right->id()->toInt() <=> $left->id()->toInt();
                }

                return $rightTimestamp <=> $leftTimestamp;
            }
        );

        return $candidates[0] ?? null;
    }

    private function jobSortTimestamp(TrackingJob $job): int
    {
        $date = $job->scheduledAt()
            ?? $job->startedAt()
            ?? $job->finishedAt()
            ?? $job->createdAt();

        return $date->getTimestamp();
    }
}
