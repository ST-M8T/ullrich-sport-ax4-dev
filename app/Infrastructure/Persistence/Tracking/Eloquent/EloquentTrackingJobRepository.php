<?php

namespace App\Infrastructure\Persistence\Tracking\Eloquent;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use App\Support\Persistence\CastsDateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTrackingJobRepository implements TrackingJobRepository
{
    use CastsDateTime;

    public function nextIdentity(): Identifier
    {
        return DB::transaction(function (): Identifier {
            $next = (int) (TrackingJobModel::query()->lockForUpdate()->max('id') ?? 0) + 1;

            return Identifier::fromInt($next);
        }, 5);
    }

    public function find(array $filters = []): iterable
    {
        $query = TrackingJobModel::query()
            ->when(isset($filters['job_type']), fn ($q) => $q->where('job_type', $filters['job_type']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc(DB::raw('COALESCE(scheduled_at, created_at)'))
            ->orderByDesc('id');

        return $query->get()->map(fn (TrackingJobModel $model) => $this->mapModel($model));
    }

    public function findDueJobs(DateTimeImmutable $cutoff, int $limit = 50): iterable
    {
        return DB::transaction(function () use ($cutoff, $limit): array {
            $now = new DateTimeImmutable;

            $models = TrackingJobModel::query()
                ->where('status', TrackingJob::STATUS_SCHEDULED)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', $cutoff);
                })
                ->orderBy(DB::raw('COALESCE(scheduled_at, created_at)'))
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($models as $model) {
                $model->status = TrackingJob::STATUS_RESERVED;
                $model->updated_at = $now;
                $model->save();
            }

            return $models
                ->map(fn (TrackingJobModel $model) => $this->mapModel($model))
                ->all();
        }, 5);
    }

    public function getById(Identifier $id): ?TrackingJob
    {
        $model = TrackingJobModel::find($id->toInt());

        return $model ? $this->mapModel($model) : null;
    }

    public function save(TrackingJob $job): void
    {
        $model = TrackingJobModel::find($job->id()->toInt()) ?? new TrackingJobModel(['id' => $job->id()->toInt()]);

        $model->job_type = $job->jobType();
        $model->status = $job->status();
        $model->scheduled_at = $job->scheduledAt();
        $model->started_at = $job->startedAt();
        $model->finished_at = $job->finishedAt();
        $model->attempt = $job->attempt();
        $model->last_error = $job->lastError();
        $model->payload = $job->payload();
        $model->result = $job->result();
        $model->save();
    }

    public function findLatestForType(string $jobType): ?TrackingJob
    {
        $model = TrackingJobModel::query()
            ->where('job_type', $jobType)
            ->orderByDesc(DB::raw('COALESCE(scheduled_at, created_at)'))
            ->orderByDesc('id')
            ->first();

        return $model ? $this->mapModel($model) : null;
    }

    private function mapModel(TrackingJobModel $model): TrackingJob
    {
        return TrackingJob::hydrate(
            Identifier::fromInt((int) $model->getKey()),
            $model->job_type,
            $model->status,
            $this->toImmutable($model->scheduled_at),
            $this->toImmutable($model->started_at),
            $this->toImmutable($model->finished_at),
            (int) $model->attempt,
            $model->last_error,
            is_array($model->payload) ? $model->payload : [],
            is_array($model->result) ? $model->result : [],
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
        );
    }
}
