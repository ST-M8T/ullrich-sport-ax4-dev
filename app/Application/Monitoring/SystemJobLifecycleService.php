<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;
use DateTimeImmutable;

final class SystemJobLifecycleService
{
    public function __construct(
        private readonly SystemJobRepository $jobs,
        private readonly SystemJobPolicyService $policies,
        private readonly SystemJobTrackingCoordinator $trackingCoordinator,
        private readonly SystemJobRetryService $retryService,
        private readonly SystemJobAlertService $alertService,
        private readonly SystemJobFailureStreakService $failureStreakService,
    ) {}

    public function find(int $id): ?SystemJobEntry
    {
        return $this->jobs->find($id);
    }

    /**
     * @return array{
     *     counts: array<string,int>,
     *     total: int,
     *     recent: array<int,SystemJobEntry>
     * }
     */
    public function summarize(?string $jobName = null, int $recentLimit = 5): array
    {
        $counts = $this->jobs->countByStatus($jobName);
        $recent = $this->normalizeIterable($this->jobs->latest($recentLimit, $jobName));

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'recent' => $recent,
        ];
    }

    /**
     * @psalm-param array<string,mixed> $payload
     */
    public function start(
        string $jobName,
        ?string $jobType = null,
        ?string $runContext = null,
        array $payload = [],
        ?DateTimeImmutable $scheduledAt = null
    ): SystemJobEntry {
        $now = new DateTimeImmutable;
        $scheduledAt ??= $now;

        $entry = SystemJobEntry::hydrate(
            0,
            $jobName,
            $jobType,
            $runContext,
            'running',
            $scheduledAt,
            $now,
            null,
            null,
            $payload,
            [],
            null,
            $now,
            $now,
        );

        return $this->jobs->create($entry);
    }

    /**
     * @psalm-param array<string,mixed> $result
     */
    public function finish(SystemJobEntry $entry, string $status, array $result = [], ?string $error = null): void
    {
        $normalizedStatus = strtolower($status);
        $now = new DateTimeImmutable;
        $duration = null;
        if ($entry->startedAt()) {
            $duration = max(0, ($now->getTimestamp() - $entry->startedAt()->getTimestamp()) * 1000);
        }

        $previousFailureStreak = $this->failureStreakService->calculate($entry);
        $currentFailureStreak = $normalizedStatus === 'failed'
            ? $previousFailureStreak + 1
            : 0;

        $policyResult = $this->applyPolicies(
            $entry,
            $normalizedStatus,
            $result,
            $error,
            $previousFailureStreak,
            $currentFailureStreak,
            $now
        );

        $finalResult = $policyResult['result'];
        $finalResult['failure_streak'] = $currentFailureStreak;
        $finalResult['status'] = $normalizedStatus;
        $finalError = $policyResult['error'];

        if (! array_key_exists('alert_triggered', $finalResult)) {
            $finalResult['alert_triggered'] = false;
        }

        if (! array_key_exists('retry_scheduled_at', $finalResult)) {
            $finalResult['retry_scheduled_at'] = null;
        }

        $finished = SystemJobEntry::hydrate(
            $entry->id(),
            $entry->jobName(),
            $entry->jobType(),
            $entry->runContext(),
            $status,
            $entry->scheduledAt(),
            $entry->startedAt(),
            $now,
            $duration,
            $entry->payload(),
            $finalResult,
            $finalError,
            $entry->createdAt(),
            $now,
        );

        $this->jobs->update($finished);
    }

    /**
     * @psalm-param array<string,mixed> $result
     *
     * @return array{result: array<string,mixed>, error: ?string}
     */
    private function applyPolicies(
        SystemJobEntry $entry,
        string $normalizedStatus,
        array $result,
        ?string $error,
        int $previousFailureStreak,
        int $currentFailureStreak,
        DateTimeImmutable $now
    ): array {
        if ($normalizedStatus === 'completed') {
            $this->trackingCoordinator->markCompleted($entry, $result);

            return ['result' => $result, 'error' => $error];
        }

        if ($normalizedStatus !== 'failed') {
            return ['result' => $result, 'error' => $error];
        }

        $policy = $this->policies->policyForEntry($entry);
        $trackingJob = $this->trackingCoordinator->markFailed($entry, $result, $error);
        if ($trackingJob) {
            $result['attempt'] = $trackingJob->attempt();
            $result['tracking_job_status'] = $trackingJob->status();
        }

        $retryPolicy = is_array($policy['retry'] ?? null) ? $policy['retry'] : [];
        $alertPolicy = is_array($policy['alert'] ?? null) ? $policy['alert'] : [];

        $result = $this->retryService->apply($retryPolicy, $entry, $trackingJob, $result, $now);
        $alertTriggered = $this->alertService->triggerIfNecessary(
            $alertPolicy,
            $entry,
            $trackingJob,
            $error,
            $previousFailureStreak,
            $currentFailureStreak
        );
        $result['alert_triggered'] = $alertTriggered;

        return ['result' => $result, 'error' => $error];
    }

    /**
     * @template T
     *
     * @param  iterable<T>  $items
     * @return array<int,T>
     */
    private function normalizeIterable(iterable $items): array
    {
        return is_array($items) ? array_values($items) : iterator_to_array($items, false);
    }
}
