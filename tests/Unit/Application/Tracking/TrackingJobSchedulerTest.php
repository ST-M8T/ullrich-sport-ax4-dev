<?php

namespace Tests\Unit\Application\Tracking;

use App\Application\Tracking\TrackingJobScheduler;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class TrackingJobSchedulerTest extends TestCase
{
    private TrackingJobRepository&MockInterface $repository;

    private TrackingJobService $jobService;

    private TrackingJobScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TrackingJobRepository::class);
        $this->jobService = new TrackingJobService($this->repository);
        $this->scheduler = new TrackingJobScheduler($this->repository, $this->jobService);
    }

    public function test_dispatch_due_jobs_invokes_callback_for_each_job(): void
    {
        $jobs = [
            $this->jobInstance(1, 'dispatch.dhl'),
            $this->jobInstance(2, 'dispatch.plenty'),
        ];

        $this->repository
            ->shouldReceive('findDueJobs')
            ->once()
            ->with(Mockery::type(DateTimeImmutable::class), 10)
            ->andReturn($jobs);

        $processed = [];

        $count = $this->scheduler->dispatchDueJobs(function (TrackingJob $job) use (&$processed): void {
            $processed[] = $job->jobType();
        }, 10);

        $this->assertSame(2, $count);
        $this->assertSame(['dispatch.dhl', 'dispatch.plenty'], $processed);
    }

    public function test_dispatch_due_jobs_returns_zero_when_no_jobs_available(): void
    {
        $this->repository
            ->shouldReceive('findDueJobs')
            ->once()
            ->with(Mockery::type(DateTimeImmutable::class), 25)
            ->andReturn([]);

        $count = $this->scheduler->dispatchDueJobs(fn () => $this->fail('Callback should not be called.'));

        $this->assertSame(0, $count);
    }

    private function jobInstance(int $id, string $type): TrackingJob
    {
        $identifier = Identifier::fromInt($id);
        $now = new DateTimeImmutable('-1 minute');

        return TrackingJob::hydrate(
            $identifier,
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
}
