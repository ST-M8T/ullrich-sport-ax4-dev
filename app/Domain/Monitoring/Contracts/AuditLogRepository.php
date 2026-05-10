<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Contracts;

use App\Domain\Monitoring\AuditLogEntry;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;

interface AuditLogRepository
{
    public function append(AuditLogEntry $entry): void;

    /**
     * @param  array<string,mixed>  $filters
     * @return iterable<AuditLogEntry>
     */
    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable;

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<AuditLogEntry>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult;
}
