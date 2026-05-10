<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface SystemJobRepository
{
    public function create(SystemJobEntry $job): SystemJobEntry;

    public function update(SystemJobEntry $job): void;

    public function find(int $id): ?SystemJobEntry;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<SystemJobEntry>
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<SystemJobEntry>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult;

    /**
     * @return array<string,int>
     */
    public function countByStatus(?string $jobName = null): array;

    /**
     * @return iterable<SystemJobEntry>
     */
    public function latest(int $limit = 5, ?string $jobName = null): iterable;
}
