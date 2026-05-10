<?php

namespace App\Http\Controllers\Tracking;

use App\Application\Tracking\Queries\ListTrackingJobs;
use App\Application\Tracking\TrackingJobService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Domain\Tracking\TrackingJob;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TrackingJobController extends Controller
{
    public function __construct(
        private readonly TrackingJobService $jobService,
        private readonly ListTrackingJobs $listJobs,
    ) {}

    public function show(int $job): JsonResponse
    {
        $identifier = Identifier::fromInt($job);
        $trackingJob = $this->jobService->get($identifier);

        if (! $trackingJob) {
            abort(404, 'Tracking job not found.');
        }

        $history = collect(iterator_to_array(($this->listJobs)([
            'job_type' => $trackingJob->jobType(),
        ])))
            ->sortByDesc(fn (TrackingJob $item) => $item->scheduledAt()?->getTimestamp() ?? $item->createdAt()->getTimestamp())
            ->take(10)
            ->map(fn (TrackingJob $item) => $this->presentJobSummary($item))
            ->values()
            ->all();

        return response()->json([
            'job' => $this->presentJobDetail($trackingJob),
            'history' => $history,
        ]);
    }

    public function retry(int $job): JsonResponse
    {
        $identifier = Identifier::fromInt($job);
        $updated = $this->jobService->retry($identifier);

        if (! $updated) {
            abort(404, 'Tracking job not found.');
        }

        return response()->json([
            'job' => $this->presentJobDetail($updated),
            'message' => 'Job wurde erneut eingeplant.',
        ]);
    }

    public function markFailed(Request $request, int $job): JsonResponse
    {
        $identifier = Identifier::fromInt($job);
        $reason = $request->input('reason');
        $updated = $this->jobService->markFailed($identifier, is_string($reason) ? $reason : null);

        if (! $updated) {
            abort(404, 'Tracking job not found.');
        }

        return response()->json([
            'job' => $this->presentJobDetail($updated),
            'message' => 'Job wurde als fehlgeschlagen markiert.',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function presentJobDetail(TrackingJob $job): array
    {
        return [
            'id' => $job->id()->toInt(),
            'job_type' => $job->jobType(),
            'status' => $job->status(),
            'attempt' => $job->attempt(),
            'scheduled_at' => $this->formatDate($job->scheduledAt()),
            'scheduled_at_iso' => $this->formatIso($job->scheduledAt()),
            'started_at' => $this->formatDate($job->startedAt()),
            'started_at_iso' => $this->formatIso($job->startedAt()),
            'finished_at' => $this->formatDate($job->finishedAt()),
            'finished_at_iso' => $this->formatIso($job->finishedAt()),
            'created_at' => $this->formatDate($job->createdAt()),
            'updated_at' => $this->formatDate($job->updatedAt()),
            'last_error' => $job->lastError(),
            'payload' => $job->payload(),
            'result' => $job->result(),
            'can_retry' => $job->status() !== TrackingJob::STATUS_RUNNING,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentJobSummary(TrackingJob $job): array
    {
        return [
            'id' => $job->id()->toInt(),
            'job_type' => $job->jobType(),
            'status' => $job->status(),
            'scheduled_at' => $this->formatDate($job->scheduledAt()) ?? $this->formatDate($job->createdAt()),
            'finished_at' => $this->formatDate($job->finishedAt()),
            'attempt' => $job->attempt(),
            'last_error' => $job->lastError(),
        ];
    }

    private function formatDate(?DateTimeImmutable $date): ?string
    {
        return $date?->format('d.m.Y H:i');
    }

    private function formatIso(?DateTimeImmutable $date): ?string
    {
        return $date?->format(\DateTimeInterface::ATOM);
    }
}
