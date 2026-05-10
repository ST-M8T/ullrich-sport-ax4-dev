<?php

namespace App\Application\Tracking;

use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;

class TrackingJobService
{
    public function __construct(private readonly TrackingJobRepository $repository) {}

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public function schedule(string $jobType, array $payload = [], ?DateTimeImmutable $scheduledAt = null): TrackingJob
    {
        $id = $this->repository->nextIdentity();
        $now = new DateTimeImmutable;

        $job = TrackingJob::schedule($id, $jobType, $payload, $now, $scheduledAt);

        $this->repository->save($job);

        return $job;
    }

    public function markStarted(Identifier $id): ?TrackingJob
    {
        $job = $this->repository->getById($id);
        if (! $job) {
            return null;
        }

        try {
            $updated = $job->start(new DateTimeImmutable);
        } catch (\Throwable) {
            return null;
        }

        $this->repository->save($updated);

        return $updated;
    }

    /**
     * @psalm-param array<string,mixed> $result
     */
    public function markFinished(Identifier $id, array $result = [], ?string $error = null): ?TrackingJob
    {
        $job = $this->repository->getById($id);
        if (! $job) {
            return null;
        }

        $now = new DateTimeImmutable;

        try {
            $updated = $error === null
                ? $job->complete($now, $result)
                : $job->fail($now, $result, $error);
        } catch (\Throwable) {
            return null;
        }

        $this->repository->save($updated);

        return $updated;
    }

    public function get(Identifier $id): ?TrackingJob
    {
        return $this->repository->getById($id);
    }

    public function retry(Identifier $id, ?DateTimeImmutable $scheduledAt = null): ?TrackingJob
    {
        $job = $this->repository->getById($id);
        if (! $job) {
            return null;
        }

        try {
            $updated = $job->retry(new DateTimeImmutable, $scheduledAt);
        } catch (\Throwable) {
            return null;
        }

        $this->repository->save($updated);

        return $updated;
    }

    public function markFailed(Identifier $id, ?string $error = null): ?TrackingJob
    {
        $message = $error !== null && trim($error) !== '' ? trim($error) : 'Marked as failed via admin panel.';

        return $this->markFinished($id, [], $message);
    }
}
