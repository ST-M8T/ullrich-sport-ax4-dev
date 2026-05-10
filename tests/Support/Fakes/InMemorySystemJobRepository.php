<?php

namespace Tests\Support\Fakes;

use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

final class InMemorySystemJobRepository implements SystemJobRepository
{
    /**
     * @var array<int,SystemJobEntry>
     */
    private array $jobs = [];

    private int $nextId = 1;

    public function create(SystemJobEntry $job): SystemJobEntry
    {
        $stored = $this->cloneWithId($job, $this->nextId++);
        $this->jobs[$stored->id()] = $stored;

        return $stored;
    }

    public function update(SystemJobEntry $job): void
    {
        $this->jobs[$job->id()] = $job;
    }

    public function find(int $id): ?SystemJobEntry
    {
        return $this->jobs[$id] ?? null;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        $filtered = $this->filterJobs($filters);

        return array_slice($filtered, $offset, $limit);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<SystemJobEntry>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        $filtered = $this->filterJobs($filters);
        $perPage = $perPage ?? max(1, count($filtered));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $items = array_slice($filtered, $offset, $perPage);
        $total = count($filtered);
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return PaginatedResult::create(
            $items,
            $total,
            $perPage,
            $page,
            $lastPage,
        );
    }

    public function countByStatus(?string $jobName = null): array
    {
        $counts = [];

        foreach ($this->jobs as $job) {
            if ($jobName !== null && $job->jobName() !== $jobName) {
                continue;
            }

            $counts[$job->status()] = ($counts[$job->status()] ?? 0) + 1;
        }

        foreach ($counts as &$value) {
            $value = (int) $value;
        }

        return $counts;
    }

    public function latest(int $limit = 5, ?string $jobName = null): iterable
    {
        $filters = [];
        if ($jobName !== null) {
            $filters['job_name'] = $jobName;
        }

        $filtered = $this->filterJobs($filters);

        return array_slice($filtered, 0, max(1, $limit));
    }

    private function cloneWithId(SystemJobEntry $job, int $id): SystemJobEntry
    {
        return SystemJobEntry::hydrate(
            $id,
            $job->jobName(),
            $job->jobType(),
            $job->runContext(),
            $job->status(),
            $job->scheduledAt(),
            $job->startedAt(),
            $job->finishedAt(),
            $job->durationMs(),
            $job->payload(),
            $job->result(),
            $job->errorMessage(),
            $job->createdAt(),
            $job->updatedAt(),
        );
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,SystemJobEntry>
     */
    private function filterJobs(array $filters = []): array
    {
        $filtered = array_filter(
            $this->jobs,
            function (SystemJobEntry $job) use ($filters) {
                if (isset($filters['job_name']) && $job->jobName() !== $filters['job_name']) {
                    return false;
                }

                if (isset($filters['status']) && $job->status() !== $filters['status']) {
                    return false;
                }

                if (isset($filters['from']) && $job->createdAt() < $filters['from']) {
                    return false;
                }

                if (isset($filters['to']) && $job->createdAt() > $filters['to']) {
                    return false;
                }

                return true;
            }
        );

        usort(
            $filtered,
            fn (SystemJobEntry $a, SystemJobEntry $b) => $b->createdAt() <=> $a->createdAt()
        );

        return array_values($filtered);
    }
}
