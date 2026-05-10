<?php

namespace App\Application\Fulfillment\Exports;

use App\Domain\Monitoring\Contracts\SystemJobRepository;
use App\Domain\Monitoring\SystemJobEntry;

final class ListShipmentExportJobs
{
    public function __construct(private readonly SystemJobRepository $jobs) {}

    /**
     * @return array<int,array{
     *     id: int,
     *     status: string,
     *     created_at: \DateTimeImmutable,
     *     started_at: \DateTimeImmutable|null,
     *     finished_at: \DateTimeImmutable|null,
     *     duration_ms: int|null,
     *     orders_total: int|null,
     *     file: string|null,
     *     file_size: int|null,
     *     error: string|null,
     *     payload: array<string,mixed>
     * }>
     */
    public function __invoke(int $limit = 20, ?string $status = null): array
    {
        $filters = ['job_name' => 'fulfillment.csv_export'];
        if ($status !== null && $status !== '') {
            $filters['status'] = $status;
        }

        $results = [];

        /** @var iterable<SystemJobEntry> $jobs */
        $jobs = $this->jobs->search($filters, $limit);

        foreach ($jobs as $job) {
            $result = $job->result();

            $results[] = [
                'id' => $job->id(),
                'status' => $job->status(),
                'created_at' => $job->createdAt(),
                'started_at' => $job->startedAt(),
                'finished_at' => $job->finishedAt(),
                'duration_ms' => $job->durationMs(),
                'orders_total' => isset($result['orders_total']) ? (int) $result['orders_total'] : null,
                'file' => isset($result['file']) && is_string($result['file']) ? $result['file'] : null,
                'file_size' => isset($result['file_size']) && is_numeric($result['file_size']) ? (int) $result['file_size'] : null,
                'error' => $job->errorMessage(),
                'payload' => $job->payload(),
            ];
        }

        return $results;
    }
}
