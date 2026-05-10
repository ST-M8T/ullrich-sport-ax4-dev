<?php

namespace Tests\Unit\Application\Monitoring;

use App\Application\Monitoring\SystemJobAlertService;
use App\Application\Monitoring\SystemJobFailureStreakService;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Application\Monitoring\SystemJobPolicyService;
use App\Application\Monitoring\SystemJobRetryService;
use App\Application\Monitoring\SystemJobTrackingCoordinator;
use App\Application\Tracking\TrackingAlertService;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemorySystemJobRepository;
use Tests\Support\Fakes\InMemoryTrackingAlertRepository;
use Tests\Support\Fakes\InMemoryTrackingJobRepository;

final class SystemJobLifecycleEndToEndTest extends TestCase
{
    public function test_failed_jobs_schedule_retry_and_trigger_alert_after_threshold(): void
    {
        $trackingJobs = new InMemoryTrackingJobRepository;
        $alerts = new InMemoryTrackingAlertRepository;
        $systemJobs = new InMemorySystemJobRepository;

        $trackingJobService = new TrackingJobService($trackingJobs);
        $trackingAlertService = new TrackingAlertService($alerts);

        $policyService = new SystemJobPolicyService([
            'tracking.sync' => [
                'job_type' => 'tracking.sync',
                'retry' => [
                    'max_attempts' => 3,
                    'backoff' => 'PT5M',
                ],
                'alert' => [
                    'threshold' => 2,
                    'alert_type' => 'tracking.job.failure',
                    'severity' => 'critical',
                ],
            ],
        ]);

        $lifecycle = new SystemJobLifecycleService(
            $systemJobs,
            $policyService,
            new SystemJobTrackingCoordinator($trackingJobService),
            new SystemJobRetryService($trackingJobService),
            new SystemJobAlertService($trackingAlertService),
            new SystemJobFailureStreakService($systemJobs),
        );

        $service = $lifecycle;

        $scheduledAt = new DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $job = $trackingJobService->schedule('tracking.sync', [], $scheduledAt);
        $trackingJobService->markStarted($job->id());

        $systemEntry = $service->start(
            'tracking-job.tracking.sync',
            'tracking',
            'dispatch',
            [
                'tracking_job_id' => $job->id()->toInt(),
                'job_type' => 'tracking.sync',
            ],
            $scheduledAt
        );

        $service->finish($systemEntry, 'failed', ['step' => 'first_attempt'], 'Transport timeout');
        $firstStored = $systemJobs->find($systemEntry->id());
        self::assertNotNull($firstStored);
        $firstResult = $firstStored->result();
        self::assertSame('failed', $firstResult['status']);
        self::assertSame(1, $firstResult['failure_streak']);
        self::assertFalse($firstResult['alert_triggered']);
        self::assertNotNull($firstResult['retry_scheduled_at']);

        $rescheduledAt = new DateTimeImmutable($firstResult['retry_scheduled_at']);
        $trackingJobService->markStarted($job->id());

        $secondEntry = $service->start(
            'tracking-job.tracking.sync',
            'tracking',
            'dispatch',
            [
                'tracking_job_id' => $job->id()->toInt(),
                'job_type' => 'tracking.sync',
            ],
            $rescheduledAt
        );

        $service->finish($secondEntry, 'failed', ['step' => 'second_attempt'], 'API rate limited');
        $secondStored = $systemJobs->find($secondEntry->id());
        self::assertNotNull($secondStored);
        $secondResult = $secondStored->result();

        self::assertSame(2, $secondResult['failure_streak']);
        self::assertTrue($secondResult['alert_triggered']);
        self::assertNotNull($alerts->getById(Identifier::fromInt(1)));
        self::assertNull($secondResult['retry_scheduled_at']);
    }
}
