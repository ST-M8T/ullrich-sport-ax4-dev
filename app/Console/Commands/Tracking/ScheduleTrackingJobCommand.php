<?php

namespace App\Console\Commands\Tracking;

use App\Application\Tracking\TrackingJobService;
use Illuminate\Console\Command;

class ScheduleTrackingJobCommand extends Command
{
    protected $signature = 'tracking:jobs:schedule
        {type : The job type identifier}
        {--payload= : JSON payload for the job}
        {--scheduled-at= : Optional ISO-8601 datetime for scheduling}';

    protected $description = 'Schedule a tracking job with optional payload and execution time';

    public function __construct(private readonly TrackingJobService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $jobType = (string) $this->argument('type');
        $payloadOption = $this->option('payload');
        $scheduledAtOption = $this->option('scheduled-at');

        $payload = [];
        if (is_string($payloadOption) && $payloadOption !== '') {
            $decoded = json_decode($payloadOption, true);
            if (! is_array($decoded)) {
                $this->error('Payload must be valid JSON.');

                return self::FAILURE;
            }
            $payload = $decoded;
        }

        $scheduledAt = null;
        if (is_string($scheduledAtOption) && $scheduledAtOption !== '') {
            try {
                $scheduledAt = new \DateTimeImmutable($scheduledAtOption);
            } catch (\Throwable $e) {
                $this->error('Invalid scheduled-at datetime: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $job = $this->service->schedule($jobType, $payload, $scheduledAt);

        $this->info(sprintf('Tracking job #%d scheduled (%s).', $job->id()->toInt(), $job->jobType()));

        return self::SUCCESS;
    }
}
