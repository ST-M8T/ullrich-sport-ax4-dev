<?php

namespace Tests\Feature\Console;

use App\Domain\Tracking\TrackingJob;
use App\Infrastructure\Persistence\Tracking\Eloquent\TrackingJobModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RetryTrackingJobCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_command_reschedules_job(): void
    {
        $job = TrackingJobModel::create([
            'job_type' => 'demo',
            'status' => TrackingJob::STATUS_FAILED,
            'scheduled_at' => Carbon::now()->subDay(),
            'finished_at' => Carbon::now()->subDay(),
            'attempt' => 1,
            'last_error' => 'Failure',
            'payload' => ['foo' => 'bar'],
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDay(),
        ]);

        $this->artisan('tracking:jobs:retry', ['job' => $job->id])
            ->assertExitCode(0);

        $this->assertDatabaseHas('tracking_jobs', [
            'id' => $job->id,
            'status' => TrackingJob::STATUS_SCHEDULED,
        ]);
    }

    public function test_retry_command_with_custom_schedule(): void
    {
        $job = TrackingJobModel::create([
            'job_type' => 'demo',
            'status' => TrackingJob::STATUS_FAILED,
            'scheduled_at' => null,
            'attempt' => 0,
            'created_at' => Carbon::now()->subDay(),
            'updated_at' => Carbon::now()->subDay(),
        ]);

        $target = Carbon::now()->addHour()->toIso8601String();

        $this->artisan('tracking:jobs:retry', [
            'job' => $job->id,
            '--at' => $target,
        ])->assertExitCode(0);

        $updated = $job->fresh();
        $this->assertNotNull($updated->scheduled_at);
        $this->assertSame($target, $updated->scheduled_at->toIso8601String());
    }
}
