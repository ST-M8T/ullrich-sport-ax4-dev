<?php

namespace Tests\Feature\Console;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use Mockery;
use Tests\TestCase;

final class TrackingScheduleJobCommandTest extends TestCase
{
    public function test_schedule_command_accepts_payload_and_datetime(): void
    {
        $service = Mockery::mock(TrackingJobService::class);
        $job = $this->job(Identifier::fromInt(15), 'dhl-sync', TrackingJob::STATUS_SCHEDULED);

        $service
            ->shouldReceive('schedule')
            ->once()
            ->with('dhl-sync', ['cursor' => 1], Mockery::type(DateTimeImmutable::class))
            ->andReturn($job);

        $this->app->instance(TrackingJobService::class, $service);

        $this->artisan('tracking:jobs:schedule', [
            'type' => 'dhl-sync',
            '--payload' => json_encode(['cursor' => 1], JSON_THROW_ON_ERROR),
            '--scheduled-at' => '2024-01-01T10:00:00+00:00',
        ])->expectsOutput('Tracking job #15 scheduled (dhl-sync).')
            ->assertExitCode(0);
    }

    public function test_schedule_command_validates_json_payload(): void
    {
        $service = Mockery::mock(TrackingJobService::class);
        $this->app->instance(TrackingJobService::class, $service);

        $this->artisan('tracking:jobs:schedule', [
            'type' => 'dhl-sync',
            '--payload' => '{invalid json}',
        ])->expectsOutput('Payload must be valid JSON.')
            ->assertExitCode(1);
    }

    private function job(Identifier $id, string $type, string $status): TrackingJob
    {
        $now = new DateTimeImmutable('-1 minute');

        return TrackingJob::hydrate(
            $id,
            $type,
            $status,
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
