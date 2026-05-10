<?php

namespace App\Console\Commands\Tracking;

use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use Illuminate\Console\Command;

class RetryTrackingJobCommand extends Command
{
    protected $signature = 'tracking:jobs:retry
        {job : ID des Tracking-Jobs}
        {--at= : Neuer Ausführungszeitpunkt (ISO8601)}';

    protected $description = 'Plant einen fehlgeschlagenen Tracking-Job erneut ein.';

    public function __construct(private readonly TrackingJobService $jobs)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $jobId = (int) $this->argument('job');
        if ($jobId <= 0) {
            $this->components->error('Bitte eine gültige Job-ID angeben.');

            return self::FAILURE;
        }

        $schedule = $this->option('at');
        $scheduledAt = null;

        if (is_string($schedule) && $schedule !== '') {
            try {
                $scheduledAt = new DateTimeImmutable($schedule);
            } catch (\Throwable) {
                $this->components->error('Ungültiges Datum für --at. Erwartet wird ein ISO-8601 Zeitstempel.');

                return self::FAILURE;
            }
        }

        $this->components->info(sprintf('Plane Tracking-Job #%d neu ein ...', $jobId));

        $job = $this->jobs->retry(Identifier::fromInt($jobId), $scheduledAt);
        if ($job === null) {
            $this->components->error('Tracking-Job wurde nicht gefunden.');

            return self::FAILURE;
        }

        $this->components->success(sprintf(
            'Job #%d ist wieder im Status "%s" und läuft %s.',
            $job->id()->toInt(),
            $job->status(),
            $job->scheduledAt()?->format(DATE_ATOM) ?? 'sofort'
        ));

        return self::SUCCESS;
    }
}
