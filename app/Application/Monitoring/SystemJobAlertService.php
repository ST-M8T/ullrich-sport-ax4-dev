<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Application\Tracking\TrackingAlertService;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Tracking\TrackingJob;

final class SystemJobAlertService
{
    public function __construct(private readonly ?TrackingAlertService $alerts = null) {}

    /**
     * @psalm-param array<string,mixed> $policy
     */
    public function triggerIfNecessary(
        array $policy,
        SystemJobEntry $entry,
        ?TrackingJob $trackingJob,
        ?string $error,
        int $previousFailureStreak,
        int $currentFailureStreak
    ): bool {
        $threshold = isset($policy['threshold']) ? (int) $policy['threshold'] : 0;
        if ($threshold <= 0) {
            return false;
        }

        if ($currentFailureStreak < $threshold) {
            return false;
        }

        if ($previousFailureStreak >= $threshold) {
            return false;
        }

        $alertType = is_string($policy['alert_type'] ?? null) ? $policy['alert_type'] : 'tracking.job.failure';
        $severity = is_string($policy['severity'] ?? null) ? $policy['severity'] : 'error';
        $channel = is_string($policy['channel'] ?? null) ? $policy['channel'] : null;
        $message = is_string($policy['message'] ?? null)
            ? $policy['message']
            : sprintf(
                'Systemjob %s (%s) ist %d Mal hintereinander fehlgeschlagen.',
                $entry->jobName(),
                $trackingJob?->jobType() ?? 'unbekannt',
                $currentFailureStreak
            );

        $metadata = [
            'system_job_id' => $entry->id(),
            'job_name' => $entry->jobName(),
            'job_type' => $trackingJob?->jobType() ?? ($entry->payload()['job_type'] ?? null),
            'failure_streak' => $currentFailureStreak,
            'previous_failure_streak' => $previousFailureStreak,
            'threshold' => $threshold,
            'payload' => $entry->payload(),
            'error' => $error,
        ];

        if ($this->alerts === null) {
            return false;
        }

        $this->alerts->raise($alertType, $severity, $message, null, $channel, $metadata);

        return true;
    }
}
