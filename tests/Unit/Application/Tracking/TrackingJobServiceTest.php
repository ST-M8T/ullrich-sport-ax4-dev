<?php

namespace Tests\Unit\Application\Tracking;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\Contracts\TrackingJobRepository;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class TrackingJobServiceTest extends TestCase
{
    private TrackingJobRepository&MockInterface $repository;

    private TrackingJobService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TrackingJobRepository::class);
        $this->service = new TrackingJobService($this->repository);
    }

    public function test_schedule_persists_job_with_defaults(): void
    {
        $identifier = Identifier::fromInt(101);

        $this->repository
            ->shouldReceive('nextIdentity')
            ->once()
            ->andReturn($identifier);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingJob $job) use ($identifier): bool {
                $this->assertTrue($job->id()->equals($identifier));
                $this->assertSame('dhl-sync', $job->jobType());
                $this->assertSame(TrackingJob::STATUS_SCHEDULED, $job->status());
                $this->assertSame(['cursor' => 42], $job->payload());
                $this->assertSame([], $job->result());
                $this->assertNotNull($job->scheduledAt());
                $this->assertNull($job->startedAt());
                $this->assertNull($job->finishedAt());

                return true;
            });

        $job = $this->service->schedule('dhl-sync', ['cursor' => 42]);

        $this->assertSame('dhl-sync', $job->jobType());
        $this->assertSame(TrackingJob::STATUS_SCHEDULED, $job->status());
        $this->assertSame(['cursor' => 42], $job->payload());
    }

    public function test_mark_started_returns_null_when_job_is_missing(): void
    {
        $identifier = Identifier::fromInt(77);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($identifier)
            ->andReturnNull();

        $this->assertNull($this->service->markStarted($identifier));
    }

    public function test_mark_started_updates_job_status_and_attempt(): void
    {
        $identifier = Identifier::fromInt(33);
        $existing = $this->jobInstance($identifier, TrackingJob::STATUS_SCHEDULED, 0);

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($identifier)
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingJob $job) use ($identifier): bool {
                $this->assertTrue($job->id()->equals($identifier));
                $this->assertSame(TrackingJob::STATUS_RUNNING, $job->status());
                $this->assertNotNull($job->startedAt());
                $this->assertSame(1, $job->attempt());
                $this->assertNull($job->finishedAt());

                return true;
            });

        $updated = $this->service->markStarted($identifier);

        $this->assertNotNull($updated);
        $this->assertSame(TrackingJob::STATUS_RUNNING, $updated->status());
        $this->assertSame(1, $updated->attempt());
    }

    public function test_mark_finished_sets_completed_status_and_result(): void
    {
        $identifier = Identifier::fromInt(501);
        $existing = $this->jobInstance($identifier, TrackingJob::STATUS_RUNNING, 1, new DateTimeImmutable('-5 minutes'));

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($identifier)
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingJob $job) use ($identifier): bool {
                $this->assertTrue($job->id()->equals($identifier));
                $this->assertSame(TrackingJob::STATUS_COMPLETED, $job->status());
                $this->assertSame(['synced' => 12], $job->result());
                $this->assertNull($job->lastError());
                $this->assertNotNull($job->finishedAt());

                return true;
            });

        $result = $this->service->markFinished($identifier, ['synced' => 12]);

        $this->assertNotNull($result);
        $this->assertSame(TrackingJob::STATUS_COMPLETED, $result->status());
        $this->assertSame(['synced' => 12], $result->result());
    }

    public function test_mark_failed_populates_error_message(): void
    {
        $identifier = Identifier::fromInt(888);
        $existing = $this->jobInstance($identifier, TrackingJob::STATUS_RUNNING, 2, new DateTimeImmutable('-10 minutes'));

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($identifier)
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingJob $job) use ($identifier): bool {
                $this->assertTrue($job->id()->equals($identifier));
                $this->assertSame(TrackingJob::STATUS_FAILED, $job->status());
                $this->assertSame('Custom failure.', $job->lastError());
                $this->assertNotNull($job->finishedAt());

                return true;
            });

        $job = $this->service->markFailed($identifier, 'Custom failure.');

        $this->assertNotNull($job);
        $this->assertSame(TrackingJob::STATUS_FAILED, $job->status());
        $this->assertSame('Custom failure.', $job->lastError());
    }

    public function test_mark_failed_uses_default_error_message_when_missing(): void
    {
        $identifier = Identifier::fromInt(999);
        $existing = $this->jobInstance($identifier, TrackingJob::STATUS_RUNNING, 3, new DateTimeImmutable('-2 minutes'));

        $this->repository
            ->shouldReceive('getById')
            ->once()
            ->with($identifier)
            ->andReturn($existing);

        $this->repository
            ->shouldReceive('save')
            ->once()
            ->withArgs(function (TrackingJob $job): bool {
                $this->assertSame('Marked as failed via admin panel.', $job->lastError());

                return true;
            });

        $job = $this->service->markFailed($identifier, '   ');

        $this->assertNotNull($job);
        $this->assertSame('Marked as failed via admin panel.', $job->lastError());
    }

    private function jobInstance(Identifier $id, string $status, int $attempt, ?DateTimeImmutable $startedAt = null): TrackingJob
    {
        $now = new DateTimeImmutable('-15 minutes');

        return TrackingJob::hydrate(
            $id,
            'dhl-sync',
            $status,
            $now,
            $startedAt,
            null,
            $attempt,
            null,
            ['cursor' => 10],
            [],
            $now,
            $now,
        );
    }
}
