<?php

namespace App\Console\Commands\Tracking;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

class CompleteTrackingJobCommand extends Command
{
    protected $signature = 'tracking:jobs:complete
        {id : Tracking job identifier}
        {--status=completed : completed|failed}
        {--result= : JSON result payload}
        {--error= : Error message when status=failed}';

    protected $description = 'Mark a tracking job as started and completed/failed with optional result payload';

    public function __construct(private readonly TrackingJobService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $id = Identifier::fromInt((int) $this->argument('id'));
        $status = strtolower((string) $this->option('status'));
        $resultOption = $this->option('result');
        $error = $this->option('error');

        $result = [];
        if (is_string($resultOption) && $resultOption !== '') {
            $decoded = json_decode($resultOption, true);
            if (! is_array($decoded)) {
                $this->error('Result must be valid JSON.');

                return self::FAILURE;
            }
            $result = $decoded;
        }

        $job = $this->service->markStarted($id);
        if (! $job) {
            $this->error('Tracking job not found.');

            return self::FAILURE;
        }

        $errorMessage = null;
        if ($status === 'failed') {
            if (! is_string($error) || $error === '') {
                $this->error('Error message required when marking job as failed.');

                return self::FAILURE;
            }
            $errorMessage = $error;
        }

        $finished = $this->service->markFinished($id, $result, $errorMessage);
        if (! $finished) {
            $this->error('Unable to mark job as finished.');

            return self::FAILURE;
        }

        $this->info(sprintf('Tracking job #%d completed with status %s.', $finished->id()->toInt(), $finished->status()));

        return self::SUCCESS;
    }
}
