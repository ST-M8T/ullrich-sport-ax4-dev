<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use App\Domain\Monitoring\Contracts\DomainEventRepository;
use App\Domain\Monitoring\DomainEventRecord;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Support\Persistence\CastsDateTime;
use App\Support\Persistence\MapsEloquentModels;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class EloquentDomainEventRepository implements DomainEventRepository
{
    use CastsDateTime;
    use MapsEloquentModels;

    public function nextIdentity(): UuidInterface
    {
        return Uuid::uuid4();
    }

    public function append(DomainEventRecord $record): void
    {
        DomainEventModel::create([
            'id' => $record->id()->toString(),
            'event_name' => $record->eventName(),
            'aggregate_type' => $record->aggregateType(),
            'aggregate_id' => $record->aggregateId(),
            'payload' => $record->payload(),
            'metadata' => $record->metadata(),
            'occurred_at' => $record->occurredAt(),
            'created_at' => $record->createdAt(),
        ]);
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        $query = $this->applyFilters(DomainEventModel::query(), $filters)
            ->orderByDesc('occurred_at')
            ->limit(max(1, $limit))
            ->offset(max(0, $offset));

        /** @var \Illuminate\Database\Eloquent\Collection<int, DomainEventModel> $models */
        $models = $query->get();

        return $this->mapEloquentCollection(
            $models,
            fn (DomainEventModel $model): DomainEventRecord => $this->mapModel($model),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<DomainEventRecord>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);
        $page = max(1, $page);

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, DomainEventModel> $paginator */
        $paginator = $this->applyFilters(DomainEventModel::query(), $filters)
            ->orderByDesc('occurred_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->mapEloquentToPaginatedResult(
            $paginator,
            fn (DomainEventModel $model): DomainEventRecord => $this->mapModel($model),
        );
    }

    /**
     * @param  Builder<DomainEventModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<DomainEventModel>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['event_name']), fn (Builder $q) => $q->where('event_name', $filters['event_name']))
            ->when(isset($filters['aggregate_type']), fn (Builder $q) => $q->where('aggregate_type', $filters['aggregate_type']))
            ->when(isset($filters['aggregate_id']), fn (Builder $q) => $q->where('aggregate_id', $filters['aggregate_id']))
            ->when(isset($filters['from']), fn (Builder $q) => $q->where('occurred_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $q) => $q->where('occurred_at', '<=', $filters['to']));
    }

    private function normalisePerPage(?int $perPage): int
    {
        $default = max(1, (int) config('performance.monitoring.page_size', 50));
        $value = $perPage !== null ? (int) $perPage : $default;

        return max(1, min(200, $value));
    }

    private function mapModel(DomainEventModel $model): DomainEventRecord
    {
        return DomainEventRecord::hydrate(
            Uuid::fromString((string) $model->getKey()),
            $model->event_name,
            $model->aggregate_type,
            $model->aggregate_id,
            is_array($model->payload) ? $model->payload : [],
            is_array($model->metadata) ? $model->metadata : [],
            $this->toImmutable($model->occurred_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }
}
