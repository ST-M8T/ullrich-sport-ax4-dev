<?php

namespace Tests\Support\Fakes;

use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\DomainEventRecord;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class NullDomainEventRepository implements DomainEventRepository
{
    /** @var array<int,DomainEventRecord> */
    private array $records = [];

    public function nextIdentity(): UuidInterface
    {
        return Uuid::uuid4();
    }

    public function append(DomainEventRecord $record): void
    {
        $this->records[] = $record;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        return array_slice($this->records, $offset, $limit);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return PaginatedResult<DomainEventRecord>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        $total = count($this->records);
        $perPage = $perPage ?? max(1, $total);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $items = array_slice($this->records, $offset, $perPage);
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return PaginatedResult::create(
            $items,
            $total,
            $perPage,
            $page,
            $lastPage,
        );
    }
}
