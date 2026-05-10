<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;

final class SystemJobTrackingCoordinator
{
    public function __construct(private readonly ?TrackingJobService $trackingJobs = null) {}

    /**
     * @psalm-param array<string,mixed> $result
     */
    public function markCompleted(SystemJobEntry $entry, array $result): ?TrackingJob
    {
        if ($this->trackingJobs === null) {
            return null;
        }

        $trackingJobId = $this->trackingJobIdFromPayload($entry);
        if ($trackingJobId === null) {
            return null;
        }

        $identifier = Identifier::fromInt($trackingJobId);
        $job = $this->trackingJobs->get($identifier);
        if (! $job) {
            return null;
        }

        if ($job->status() === 'completed') {
            return $job;
        }

        return $this->trackingJobs->markFinished($identifier, $result);
    }

    /**
     * @psalm-param array<string,mixed> $result
     */
    public function markFailed(SystemJobEntry $entry, array $result, ?string $error): ?TrackingJob
    {
        if ($this->trackingJobs === null) {
            return null;
        }

        $trackingJobId = $this->trackingJobIdFromPayload($entry);
        if ($trackingJobId === null) {
            return null;
        }

        $identifier = Identifier::fromInt($trackingJobId);

        return $this->trackingJobs->markFinished($identifier, $result, $error);
    }

    private function trackingJobIdFromPayload(SystemJobEntry $entry): ?int
    {
        $payload = $entry->payload();
        $rawId = $payload['tracking_job_id'] ?? $payload['job_id'] ?? null;

        if ($rawId === null) {
            return null;
        }

        if (is_int($rawId)) {
            return $rawId;
        }

        if (is_string($rawId) && ctype_digit($rawId)) {
            return (int) $rawId;
        }

        if (is_numeric($rawId)) {
            return (int) $rawId;
        }

        return null;
    }
}
