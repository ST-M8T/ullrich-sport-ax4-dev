<?php

namespace App\Application\Tracking\Resources;

use App\Domain\Tracking\TrackingJob;

final class TrackingJobResource
{
    private function __construct(private readonly TrackingJob $job) {}

    public static function fromJob(TrackingJob $job): self
    {
        return new self($job);
    }

    public function domain(): TrackingJob
    {
        return $this->job;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->job->id()->toInt(),
            'job_type' => $this->job->jobType(),
            'status' => $this->job->status(),
            'scheduled_at' => $this->job->scheduledAt()?->format(DATE_ATOM),
            'started_at' => $this->job->startedAt()?->format(DATE_ATOM),
            'finished_at' => $this->job->finishedAt()?->format(DATE_ATOM),
            'attempt' => $this->job->attempt(),
            'last_error' => $this->job->lastError(),
            'payload' => $this->job->payload(),
            'result' => $this->job->result(),
            'created_at' => $this->job->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->job->updatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (! method_exists($this->job, $name)) {
            throw new \BadMethodCallException(sprintf('Method %s::%s does not exist.', TrackingJob::class, $name));
        }

        return $this->job->{$name}(...$arguments);
    }
}
