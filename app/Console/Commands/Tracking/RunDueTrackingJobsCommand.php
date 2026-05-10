<?php

namespace App\Console\Commands\Tracking;

use App\Application\Monitoring\SystemJobLifecycleService;
use App\Application\Tracking\TrackingJobScheduler;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Tracking\TrackingJob;
use Illuminate\Console\Command;

class RunDueTrackingJobsCommand extends Command
{
    protected $signature = 'tracking:jobs:run-due {--limit=25}';

    protected $description = 'Pull scheduled tracking jobs and mark them running for asynchronous processing';

    public function __construct(
        private readonly TrackingJobScheduler $scheduler,
        private readonly TrackingJobService $jobService,
        private readonly SystemJobLifecycleService $systemJobs,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: 25;

        $dispatched = $this->scheduler->dispatchDueJobs(function (TrackingJob $job): void {
            $started = $this->jobService->markStarted($job->id());
            $jobName = sprintf('tracking-job.%s', $job->jobType());
            $scheduledAt = $job->scheduledAt();

            $systemEntry = $this->systemJobs->start(
                $jobName,
                'tracking',
                'dispatch',
                [
                    'tracking_job_id' => $job->id()->toInt(),
                    'job_type' => $job->jobType(),
                    'scheduled_at' => $scheduledAt?->format(\DateTimeInterface::ATOM),
                    'attempt' => $started?->attempt() ?? ($job->attempt() + 1),
                ],
                $scheduledAt
            );

            $this->info(sprintf('Job #%d (%s) scheduled for processing.', $job->id()->toInt(), $job->jobType()));

            // Placeholder for queue integration: mark as completed immediately
            $this->systemJobs->finish($systemEntry, 'queued');
        }, $limit);

        $this->info(sprintf('%d tracking jobs dispatched.', $dispatched));

        return self::SUCCESS;
    }
}
