<?php

namespace App\Infrastructure\Persistence\Monitoring\Eloquent;

use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Shared\ValueObjects\Pagination\PaginatedResult;
use App\Support\Persistence\CastsDateTime;
use App\Support\Persistence\MapsEloquentModels;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EloquentSystemJobRepository implements SystemJobRepository
{
    use CastsDateTime;
    use MapsEloquentModels;

    public function create(SystemJobEntry $job): SystemJobEntry
    {
        $model = SystemJobModel::create([
            'job_name' => $job->jobName(),
            'job_type' => $job->jobType(),
            'run_context' => $job->runContext(),
            'status' => $job->status(),
            'scheduled_at' => $job->scheduledAt(),
            'started_at' => $job->startedAt(),
            'finished_at' => $job->finishedAt(),
            'duration_ms' => $job->durationMs(),
            'payload' => $job->payload(),
            'result' => $job->result(),
            'error_message' => $job->errorMessage(),
        ]);

        $this->bumpCacheVersion();

        return $this->mapModel($model);
    }

    public function update(SystemJobEntry $job): void
    {
        SystemJobModel::query()->where('id', $job->id())->update([
            'status' => $job->status(),
            'scheduled_at' => $job->scheduledAt(),
            'started_at' => $job->startedAt(),
            'finished_at' => $job->finishedAt(),
            'duration_ms' => $job->durationMs(),
            'payload' => $job->payload(),
            'result' => $job->result(),
            'error_message' => $job->errorMessage(),
        ]);

        $this->bumpCacheVersion();
    }

    public function find(int $id): ?SystemJobEntry
    {
        $model = SystemJobModel::query()->find($id);

        return $model ? $this->mapModel($model) : null;
    }

    public function search(array $filters = [], int $limit = 100, int $offset = 0): iterable
    {
        $query = $this->applyFilters(SystemJobModel::query(), $filters)
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->offset(max(0, $offset));

        /** @var \Illuminate\Database\Eloquent\Collection<int, SystemJobModel> $models */
        $models = $query->get();

        return $this->mapEloquentCollection(
            $models,
            fn (SystemJobModel $model): SystemJobEntry => $this->mapModel($model),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return PaginatedResult<SystemJobEntry>
     */
    public function paginate(array $filters = [], ?int $perPage = null, int $page = 1): PaginatedResult
    {
        $perPage = $this->normalisePerPage($perPage);
        $page = max(1, $page);

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, SystemJobModel> $paginator */
        $paginator = $this->applyFilters(SystemJobModel::query(), $filters)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->mapEloquentToPaginatedResult(
            $paginator,
            fn (SystemJobModel $model): SystemJobEntry => $this->mapModel($model),
        );
    }

    public function countByStatus(?string $jobName = null): array
    {
        $cacheKey = $this->cacheKey('count_by_status', ['job_name' => $jobName]);

        return Cache::remember($cacheKey, now()->addSeconds($this->cacheTtl()), function () use ($jobName): array {
            $query = SystemJobModel::query()
                ->when($jobName !== null, fn ($q) => $q->where('job_name', $jobName))
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status');

            $counts = $query->pluck('aggregate', 'status')->all();

            return array_map(static fn ($value) => (int) $value, $counts);
        });
    }

    public function latest(int $limit = 5, ?string $jobName = null): iterable
    {
        $cacheKey = $this->cacheKey('latest', [
            'job_name' => $jobName,
            'limit' => max(1, $limit),
        ]);

        $items = Cache::remember($cacheKey, now()->addSeconds($this->cacheTtl()), function () use ($jobName, $limit): array {
            $query = SystemJobModel::query()
                ->when($jobName !== null, fn ($q) => $q->where('job_name', $jobName))
                ->orderByDesc('created_at')
                ->limit(max(1, $limit));

            return $query->get()->map(fn (SystemJobModel $model) => $this->mapModel($model))->all();
        });

        return $items;
    }

    /**
     * @param  Builder<SystemJobModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<SystemJobModel>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(isset($filters['job_name']), fn (Builder $q) => $q->where('job_name', $filters['job_name']))
            ->when(isset($filters['status']), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when(isset($filters['from']), fn (Builder $q) => $q->where('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn (Builder $q) => $q->where('created_at', '<=', $filters['to']));
    }

    private function normalisePerPage(?int $perPage): int
    {
        $default = max(1, (int) config('performance.monitoring.page_size', 50));
        $value = $perPage !== null ? (int) $perPage : $default;

        return max(1, min(200, $value));
    }

    private function cacheTtl(): int
    {
        return max(1, (int) config('performance.monitoring.cache_ttl', 120));
    }

    /**
     * @param  array<string,mixed>  $criteria
     */
    private function cacheKey(string $suffix, array $criteria = []): string
    {
        $version = $this->currentCacheVersion();
        $criteriaHash = $criteria === []
            ? '0'
            : md5(json_encode($criteria, JSON_THROW_ON_ERROR));

        return sprintf('monitoring:system-jobs:%s:%s:%s', $version, $suffix, $criteriaHash);
    }

    private function cacheVersionKey(): string
    {
        return 'monitoring:system-jobs:cache-version';
    }

    private function currentCacheVersion(): int
    {
        $version = Cache::get($this->cacheVersionKey());

        if (! is_int($version) || $version <= 0) {
            $version = 1;
            Cache::forever($this->cacheVersionKey(), $version);
        }

        return $version;
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever($this->cacheVersionKey(), $this->currentCacheVersion() + 1);
    }

    private function mapModel(SystemJobModel $model): SystemJobEntry
    {
        return SystemJobEntry::hydrate(
            (int) $model->getKey(),
            $model->job_name,
            $model->job_type,
            $model->run_context,
            $model->status,
            $this->toImmutable($model->scheduled_at),
            $this->toImmutable($model->started_at),
            $this->toImmutable($model->finished_at),
            $model->duration_ms !== null ? (int) $model->duration_ms : null,
            is_array($model->payload) ? $model->payload : [],
            is_array($model->result) ? $model->result : [],
            $model->error_message,
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
