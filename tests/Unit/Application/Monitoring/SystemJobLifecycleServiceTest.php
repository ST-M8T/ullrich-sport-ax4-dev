<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Monitoring;

use App\Application\Monitoring\SystemJobAlertService;
use App\Application\Monitoring\SystemJobFailureStreakService;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Application\Monitoring\SystemJobPolicyService;
use App\Application\Monitoring\SystemJobRetryService;
use App\Application\Monitoring\SystemJobTrackingCoordinator;
use App\Application\Tracking\TrackingAlertService;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\SystemJobEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemorySystemJobRepository;
use Tests\Support\Fakes\InMemoryTrackingAlertRepository;
use Tests\Support\Fakes\InMemoryTrackingJobRepository;

final class SystemJobLifecycleServiceTest extends TestCase
{
    private InMemorySystemJobRepository $systemJobs;

    private TrackingJobService $trackingJobs;

    private TrackingAlertService $trackingAlerts;

    private SystemJobLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->systemJobs = new InMemorySystemJobRepository;
        $this->trackingJobs = new TrackingJobService(new InMemoryTrackingJobRepository);
        $this->trackingAlerts = new TrackingAlertService(new InMemoryTrackingAlertRepository);

        $this->service = new SystemJobLifecycleService(
            $this->systemJobs,
            new SystemJobPolicyService,
            new SystemJobTrackingCoordinator($this->trackingJobs),
            new SystemJobRetryService($this->trackingJobs),
            new SystemJobAlertService($this->trackingAlerts),
            new SystemJobFailureStreakService($this->systemJobs),
        );
    }

    public function test_start_creates_running_job_entry(): void
    {
        $entry = $this->service->start('tracking-job.demo', 'tracking', 'dispatch', ['foo' => 'bar'], new DateTimeImmutable);

        $this->assertSame('tracking-job.demo', $entry->jobName());
        $this->assertSame('running', $entry->status());

        $stored = $this->systemJobs->find($entry->id());
        $this->assertInstanceOf(SystemJobEntry::class, $stored);
        $this->assertSame('running', $stored->status());
        $this->assertSame(['foo' => 'bar'], $stored->payload());
    }

    public function test_finish_updates_status_and_result(): void
    {
        $entry = $this->service->start('tracking-job.sync', 'tracking', 'dispatch', [
            'tracking_job_id' => 1,
            'job_type' => 'tracking.sync',
        ], new DateTimeImmutable('-5 minutes'));

        $this->service->finish($entry, 'completed', ['processed' => 5]);

        $updated = $this->systemJobs->find($entry->id());
        $this->assertInstanceOf(SystemJobEntry::class, $updated);
        $this->assertSame('completed', $updated->status());
        $this->assertSame(['processed' => 5, 'failure_streak' => 0, 'status' => 'completed', 'alert_triggered' => false, 'retry_scheduled_at' => null], $updated->result());
    }

    public function test_summarize_returns_counts_and_recent_jobs(): void
    {
        $first = $this->service->start('tracking-job.sync', 'tracking', 'dispatch', [], new DateTimeImmutable('-10 minutes'));
        $second = $this->service->start('tracking-job.sync', 'tracking', 'dispatch', [], new DateTimeImmutable('-5 minutes'));

        $this->service->finish($first, 'completed', []);
        $this->service->finish($second, 'failed', [], 'Boom');

        $summary = $this->service->summarize('tracking-job.sync', 5);

        $this->assertArrayHasKey('counts', $summary);
        $this->assertArrayHasKey('recent', $summary);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['counts']['completed'] ?? 0);
        $this->assertSame(1, $summary['counts']['failed'] ?? 0);
        $this->assertCount(2, $summary['recent']);
        $this->assertSame('tracking-job.sync', $summary['recent'][0]->jobName());
    }
}
