<?php

namespace Tests\Unit\Tracking;

use App\Application\Tracking\TrackingJobScheduler;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemoryTrackingJobRepository;

final class TrackingJobSchedulerTest extends TestCase
{
    public function test_dispatch_schedules_initial_recurring_job(): void
    {
        $repository = new InMemoryTrackingJobRepository;
        $service = new TrackingJobService($repository);
        $scheduler = new TrackingJobScheduler($repository, $service, [
            'tracking.sync' => [
                'job_type' => 'tracking.sync',
                'frequency' => 'PT15M',
            ],
        ]);

        $now = new DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $dispatched = [];

        $scheduler->dispatchDueJobs(function (TrackingJob $job) use (&$dispatched): void {
            $dispatched[] = $job;
        }, 25, $now);

        $this->assertCount(1, $dispatched);
        $this->assertSame('tracking.sync', $dispatched[0]->jobType());
        $this->assertEquals($now, $dispatched[0]->scheduledAt());
    }

    public function test_dispatch_catches_up_missed_intervals(): void
    {
        $repository = new InMemoryTrackingJobRepository;
        $service = new TrackingJobService($repository);
        $scheduler = new TrackingJobScheduler($repository, $service, [
            'tracking.sync' => [
                'job_type' => 'tracking.sync',
                'frequency' => 'PT1H',
            ],
        ]);

        $initial = TrackingJob::hydrate(
            Identifier::fromInt(1),
            'tracking.sync',
            TrackingJob::STATUS_COMPLETED,
            new DateTimeImmutable('2024-01-01T09:00:00+00:00'),
            new DateTimeImmutable('2024-01-01T09:05:00+00:00'),
            new DateTimeImmutable('2024-01-01T09:10:00+00:00'),
            1,
            null,
            [],
            [],
            new DateTimeImmutable('2024-01-01T08:50:00+00:00'),
            new DateTimeImmutable('2024-01-01T09:10:00+00:00'),
        );
        $repository->save($initial);

        $now = new DateTimeImmutable('2024-01-01T12:30:00+00:00');
        $scheduler->dispatchDueJobs(
            static function (): void {},
            25,
            $now
        );

        $jobs = iterator_to_array($repository->find(['job_type' => 'tracking.sync']));
        $scheduledTimes = array_map(
            fn (TrackingJob $job) => $job->scheduledAt()?->format(\DateTimeInterface::ATOM),
            $jobs
        );

        $this->assertContains('2024-01-01T10:00:00+00:00', $scheduledTimes);
        $this->assertContains('2024-01-01T11:00:00+00:00', $scheduledTimes);
        $this->assertContains('2024-01-01T12:00:00+00:00', $scheduledTimes);
    }
}
