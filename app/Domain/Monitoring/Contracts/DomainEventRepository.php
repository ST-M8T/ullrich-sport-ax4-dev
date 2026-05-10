<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\DomainEventRecord;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Ramsey\Uuid\UuidInterface;

interface DomainEventRepository
{
    public function nextIdentity(): UuidInterface;

    public function append(DomainEventRecord $record): void;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<DomainEventRecord>
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<DomainEventRecord>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult;
}
