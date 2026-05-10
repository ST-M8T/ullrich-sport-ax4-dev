<?php

declare(strict_types=1);

namespace App\Application\Monitoring;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Tracking\TrackingJob;
use DateInterval;
use DateTimeImmutable;

final class SystemJobRetryService
{
    public function __construct(private readonly ?TrackingJobService $trackingJobs = null) {}

    /**
     * @psalm-param array<string,mixed> $policy
     * @psalm-param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    public function apply(
        array $policy,
        SystemJobEntry $entry,
        ?TrackingJob $trackingJob,
        array $result,
        DateTimeImmutable $now
    ): array {
        if ($trackingJob === null) {
            return $result;
        }

        $maxAttempts = isset($policy['max_attempts']) ? (int) $policy['max_attempts'] : 0;
        if ($maxAttempts <= 0) {
            $result['retry_scheduled_at'] = null;

            return $result;
        }

        if ($trackingJob->attempt() >= $maxAttempts) {
            $result['retry_scheduled_at'] = null;

            return $result;
        }

        $backoffSpec = $policy['backoff'] ?? $policy['delay'] ?? $policy['wait'] ?? null;
        $interval = $this->resolveInterval($backoffSpec, 'PT10M');
        if ($interval->invert === 1 || $this->isZeroInterval($interval)) {
            $interval = $this->resolveInterval(null, 'PT10M');
        }

        $scheduledAt = $now->add($interval);
        if ($this->trackingJobs === null) {
            return $result;
        }

        $updated = $this->trackingJobs->retry($trackingJob->id(), $scheduledAt);

        $result['retry_scheduled_at'] = $scheduledAt->format(\DateTimeInterface::ATOM);
        if ($updated) {
            $result['retry_attempt'] = $updated->attempt();
        }

        return $result;
    }

    private function resolveInterval(mixed $value, string $default): DateInterval
    {
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateInterval(trim($value));
            } catch (\Exception) {
                // ignore invalid format
            }
        }

        if (is_numeric($value)) {
            $seconds = (int) $value;
            if ($seconds > 0) {
                $interval = DateInterval::createFromDateString($seconds.' seconds');
                if ($interval instanceof DateInterval) {
                    return $interval;
                }
            }
        }

        return new DateInterval($default);
    }

    private function isZeroInterval(DateInterval $interval): bool
    {
        return $interval->y === 0
            && $interval->m === 0
            && $interval->d === 0
            && $interval->h === 0
            && $interval->i === 0
            && $interval->s === 0
            && (int) $interval->f === 0;
    }
}
