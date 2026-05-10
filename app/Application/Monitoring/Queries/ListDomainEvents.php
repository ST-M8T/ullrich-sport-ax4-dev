<?php

namespace App\Application\Monitoring\Queries;

use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\DomainEventRecord;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use DateTimeImmutable;

final class ListDomainEvents
{
    public function __construct(private readonly DomainEventRepository $repository) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<DomainEventRecord>
     */
    public function __invoke(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        return $this->repository->paginate(
            $this->normalizeFilters($filters),
            $perPage ?? $this->defaultPerPage(),
            max(1, $page)
        );
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<int,DomainEventRecord>
     */
    public function export(array $filters = [], int $limit = 500): array
    {
        return $this->normalizeIterable(
            $this->repository->search($this->normalizeFilters($filters), max(1, $limit), 0)
        );
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array<string,mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach (['event_name', 'aggregate_type', 'aggregate_id'] as $key) {
            if (isset($filters[$key])) {
                $value = trim((string) $filters[$key]);
                if ($value !== '') {
                    $normalized[$key] = $value;
                }
            }
        }

        foreach (['from', 'to'] as $key) {
            if (! isset($filters[$key])) {
                continue;
            }

            $value = $filters[$key];

            if ($value instanceof DateTimeImmutable) {
                $normalized[$key] = $value;

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                try {
                    $normalized[$key] = new DateTimeImmutable(trim($value));
                } catch (\Exception) {
                    // ignore invalid dates
                }
            }
        }

        return $normalized;
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

    private function defaultPerPage(): int
    {
        return max(1, (int) config('performance.monitoring.page_size', 50));
    }
}
