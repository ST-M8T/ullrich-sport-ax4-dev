<?php

namespace Tests\Feature\Console;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class TrackingCompleteJobCommandTest extends TestCase
{
    public function test_complete_command_marks_job_finished(): void
    {
        $service = Mockery::mock(TrackingJobService::class);
        $jobId = Identifier::fromInt(8);
        $job = $this->job($jobId, TrackingJob::STATUS_RUNNING);
        $completed = $this->job($jobId, TrackingJob::STATUS_COMPLETED);

        $service
            ->shouldReceive('markStarted')
            ->once()
            ->with(Mockery::on(fn ($identifier) => $identifier instanceof Identifier && $identifier->toInt() === 8))
            ->andReturn($job);

        $service
            ->shouldReceive('markFinished')
            ->once()
            ->with(Mockery::on(fn ($identifier) => $identifier instanceof Identifier && $identifier->toInt() === 8), ['processed' => 3], null)
            ->andReturn($completed);

        $this->app->instance(TrackingJobService::class, $service);

        $this->artisan('tracking:jobs:complete', [
            'id' => '8',
            '--status' => 'completed',
            '--result' => json_encode(['processed' => 3], JSON_THROW_ON_ERROR),
        ])->expectsOutput('Tracking job #8 completed with status completed.')
            ->assertExitCode(0);
    }

    public function test_complete_command_requires_error_for_failed_status(): void
    {
        $service = Mockery::mock(TrackingJobService::class);
        $jobId = Identifier::fromInt(5);
        $job = $this->job($jobId, TrackingJob::STATUS_RUNNING);

        $service
            ->shouldReceive('markStarted')
            ->once()
            ->with(Mockery::on(fn ($identifier) => $identifier instanceof Identifier && $identifier->toInt() === 5))
            ->andReturn($job);

        $this->app->instance(TrackingJobService::class, $service);

        $this->artisan('tracking:jobs:complete', [
            'id' => '5',
            '--status' => 'failed',
        ])->expectsOutput('Error message required when marking job as failed.')
            ->assertExitCode(1);
    }

    public function test_complete_command_handles_missing_job(): void
    {
        $service = Mockery::mock(TrackingJobService::class);
        $jobId = Identifier::fromInt(99);

        $service
            ->shouldReceive('markStarted')
            ->once()
            ->with(Mockery::on(fn ($identifier) => $identifier instanceof Identifier && $identifier->toInt() === 99))
            ->andReturnNull();

        $this->app->instance(TrackingJobService::class, $service);

        $this->artisan('tracking:jobs:complete', [
            'id' => '99',
        ])->expectsOutput('Tracking job not found.')
            ->assertExitCode(1);
    }

    private function job(Identifier $id, string $status): TrackingJob
    {
        $now = new DateTimeImmutable('-2 minutes');

        return TrackingJob::hydrate(
            $id,
            'dhl-sync',
            $status,
            $now,
            $status !== TrackingJob::STATUS_SCHEDULED ? $now : null,
            $status === TrackingJob::STATUS_COMPLETED ? new DateTimeImmutable : null,
            1,
            null,
            [],
            [],
            $now,
            new DateTimeImmutable,
        );
    }
}
