<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use App\Domain\Monitoring\AuditLogEntry;
use App\Domain\Monitoring\Contracts\AuditLogRepository;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Support\Persistence\CastsDateTime;
use App\Support\Persistence\MapsEloquentModels;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAuditLogRepository implements AuditLogRepository
{
    use CastsDateTime;
    use MapsEloquentModels;

    public function append(AuditLogEntry $entry): void
    {
        AuditLogModel::create([
            'actor_type' => $entry->actorType(),
            'actor_id' => $entry->actorId(),
            'actor_name' => $entry->actorName(),
            'action' => $entry->action(),
            'context' => $entry->context(),
            'ip_address' => $entry->ipAddress(),
            'user_agent' => $entry->userAgent(),
            'created_at' => $entry->createdAt(),
        ]);
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        $query = $this->applyFilters(AuditLogModel::query(), $filters)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->offset(max(0, $offset));

        /** @var \Illuminate\Database\Eloquent\Collection<int, AuditLogModel> $models */
        $models = $query->get();

        return $this->mapEloquentCollection(
            $models,
            fn (AuditLogModel $model): AuditLogEntry => $this->mapModel($model),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<AuditLogEntry>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);
        $page = max(1, $page);

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, AuditLogModel> $paginator */
        $paginator = $this->applyFilters(AuditLogModel::query(), $filters)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->mapEloquentToPaginatedResult(
            $paginator,
            fn (AuditLogModel $model): AuditLogEntry => $this->mapModel($model),
        );
    }

    /**
     * @param  Builder<AuditLogModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLogModel>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['actor_type']), fn (Builder $q) => $q->where('actor_type', $filters['actor_type']))
            ->when($filters['username'] ?? null, function (Builder $q, $value): void {
                $q->where(static function (Builder $inner) use ($value): void {
                    $inner->where('actor_id', $value)->orWhere('actor_name', $value);
                });
            })
            ->when(isset($filters['action']), fn (Builder $q) => $q->where('action', $filters['action']))
            ->when(isset($filters['ip_address']), fn (Builder $q) => $q->where('ip_address', $filters['ip_address']))
            ->when(isset($filters['from']), fn (Builder $q) => $q->where('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $q) => $q->where('created_at', '<=', $filters['to']));
    }

    private function normalisePerPage(?int $perPage): int
    {
        $default = max(1, (int) config('performance.monitoring.page_size', 50));
        $value = $perPage !== null ? (int) $perPage : $default;

        return max(1, min(200, $value));
    }

    private function mapModel(AuditLogModel $model): AuditLogEntry
    {
        return AuditLogEntry::hydrate(
            (int) $model->getKey(),
            $model->actor_type,
            $model->actor_id,
            $model->actor_name,
            $model->action,
            is_array($model->context) ? $model->context : [],
            $model->ip_address,
            $model->user_agent,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
        );
    }
}
