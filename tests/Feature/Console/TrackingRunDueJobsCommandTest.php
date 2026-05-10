<?php

namespace Tests\Feature\Console;

use App\Application\Monitoring\SystemJobAlertService;
use App\Application\Monitoring\SystemJobFailureStreakService;
use App\Application\Monitoring\SystemJobLifecycleService;
use App\Application\Monitoring\SystemJobPolicyService;
use App\Application\Monitoring\SystemJobRetryService;
use App\Application\Monitoring\SystemJobTrackingCoordinator;
use App\Application\Tracking\TrackingJobScheduler;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class TrackingRunDueJobsCommandTest extends TestCase
{
    public function test_run_due_jobs_dispatches_jobs_and_records_system_job(): void
    {
        $scheduler = Mockery::mock(TrackingJobScheduler::class);
        $jobsService = Mockery::mock(TrackingJobService::class);
        $jobsRepository = Mockery::mock(SystemJobRepository::class);
        $policyService = new SystemJobPolicyService;
        $trackingCoordinator = new SystemJobTrackingCoordinator;
        $retryService = new SystemJobRetryService;
        $alertService = new SystemJobAlertService;
        $failureStreak = new SystemJobFailureStreakService($jobsRepository);
        $systemJobs = new SystemJobLifecycleService(
            $jobsRepository,
            $policyService,
            $trackingCoordinator,
            $retryService,
            $alertService,
            $failureStreak
        );

        $job = $this->trackingJob(Identifier::fromInt(21), 'sync');
        $systemEntry = $this->systemJobEntry(1, 'tracking-job.sync');

        $scheduler
            ->shouldReceive('dispatchDueJobs')
            ->once()
            ->with(Mockery::type('callable'), 25)
            ->andReturnUsing(function (callable $callback) use ($job): int {
                $callback($job);

                return 1;
            });

        $jobsService
            ->shouldReceive('markStarted')
            ->once()
            ->with($job->id())
            ->andReturn(
                TrackingJob::hydrate(
                    $job->id(),
                    $job->jobType(),
                    TrackingJob::STATUS_RUNNING,
                    $job->scheduledAt(),
                    new DateTimeImmutable,
                    null,
                    $job->attempt() + 1,
                    null,
                    $job->payload(),
                    $job->result(),
                    $job->createdAt(),
                    new DateTimeImmutable,
                )
            );

        $jobsRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (SystemJobEntry $entry) use ($job): bool {
                $this->assertSame('tracking-job.'.$job->jobType(), $entry->jobName());
                $this->assertSame('tracking', $entry->jobType());
                $this->assertSame('dispatch', $entry->runContext());
                $this->assertSame($job->id()->toInt(), $entry->payload()['tracking_job_id'] ?? null);

                return true;
            }))
            ->andReturn($systemEntry);

        $jobsRepository
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (SystemJobEntry $entry): bool {
                $this->assertSame('queued', strtolower($entry->status()));

                return true;
            }));

        $jobsRepository
            ->shouldReceive('latest')
            ->once()
            ->with(10, 'tracking-job.'.$job->jobType())
            ->andReturn([]);

        $this->app->instance(TrackingJobScheduler::class, $scheduler);
        $this->app->instance(TrackingJobService::class, $jobsService);
        $this->app->instance(SystemJobLifecycleService::class, $systemJobs);

        $this->artisan('tracking:jobs:run-due')
            ->expectsOutput('Job #21 (sync) scheduled for processing.')
            ->expectsOutput('1 tracking jobs dispatched.')
            ->assertExitCode(0);
    }

    private function trackingJob(Identifier $id, string $type): TrackingJob
    {
        $now = new DateTimeImmutable('-1 minute');

        return TrackingJob::hydrate(
            $id,
            $type,
            TrackingJob::STATUS_RESERVED,
            $now,
            null,
            null,
            0,
            null,
            [],
            [],
            $now,
            $now,
        );
    }

    private function systemJobEntry(int $id, string $jobName): SystemJobEntry
    {
        $now = new DateTimeImmutable('-1 minute');

        return SystemJobEntry::hydrate(
            $id,
            $jobName,
            'tracking',
            'dispatch',
            'running',
            $now,
            $now,
            null,
            null,
            ['tracking_job_id' => 21],
            [],
            null,
            $now,
            $now,
        );
    }
}
