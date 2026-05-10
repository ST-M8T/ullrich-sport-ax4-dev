<?php

namespace App\Http\Controllers\Monitoring;

use App\Application\Monitoring\Queries\ListSystemJobs;
use App\Domain\Monitoring\SystemJobEntry;
use App\Http\Controllers\Monitoring\Concerns\InteractsWithTimeFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SystemJobController
{
    use InteractsWithTimeFilters;

    public function __construct(private readonly ListSystemJobs $listJobs) {}

    public function index(Request $request): View|StreamedResponse
    {
        [$queryFilters, $displayFilters] = $this->prepareFilters($request);
        $perPage = $this->determinePerPage($request);
        $page = max(1, (int) $request->query('page', 1));

        if ($this->shouldExportCsv($request)) {
            $jobs = $this->listJobs->export($queryFilters, $this->determineExportLimit($request));

            return $this->exportCsv($jobs);
        }

        $jobs = ($this->listJobs)($queryFilters, $perPage, $page);

        return view('monitoring.system-jobs.index', [
            'jobs' => $jobs,
            'filters' => array_merge($displayFilters, [
                'per_page' => $perPage,
            ]),
            'timeRanges' => $this->timeRangeOptions(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    private function determinePerPage(Request $request): int
    {
        $default = (int) config('performance.monitoring.page_size', 50);

        return max(1, min(200, (int) $request->query('per_page', $default)));
    }

    private function determineExportLimit(Request $request): int
    {
        $default = 500;

        return max(1, min(5000, (int) $request->query('limit', $default)));
    }

    private function shouldExportCsv(Request $request): bool
    {
        return strtolower((string) $request->query('export', '')) === 'csv';
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string,string>}
     */
    private function prepareFilters(Request $request): array
    {
        $display = [
            'job_name' => trim((string) $request->query('job_name', '')),
            'status' => trim((string) $request->query('status', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'time_range' => trim((string) $request->query('time_range', '')),
        ];

        [$rangeFrom, $rangeTo] = $this->resolveTimeRange($display['time_range'] ?: null);

        $query = [];

        foreach (['job_name', 'status'] as $key) {
            if ($display[$key] !== '') {
                $query[$key] = $display[$key];
            }
        }

        $from = $rangeFrom ?? $this->parseDate($display['from']);
        $to = $rangeTo ?? $this->parseDate($display['to']);

        $display['from'] = $this->formatDateForInput($from);
        $display['to'] = $this->formatDateForInput($to);

        if ($from) {
            $query['from'] = $from;
        }

        if ($to) {
            $query['to'] = $to;
        }

        return [$query, $display];
    }

    /**
     * @return array<string,string>
     */
    private function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'running' => 'Running',
            'queued' => 'Queued',
            'succeeded' => 'Succeeded',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
        ];
    }

    /**
     * @param  iterable<SystemJobEntry>  $jobs
     */
    private function exportCsv(iterable $jobs): StreamedResponse
    {
        $rows = is_array($jobs) ? $jobs : iterator_to_array($jobs, false);

        $callback = static function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'ID',
                'Name',
                'Typ',
                'Status',
                'Geplant',
                'Gestartet',
                'Beendet',
                'Dauer (ms)',
                'Run Context',
                'Payload',
                'Result',
                'Fehler',
            ], ';');

            foreach ($rows as $job) {
                fputcsv($handle, [
                    $job->id(),
                    $job->jobName(),
                    $job->jobType() ?? '',
                    $job->status(),
                    $job->scheduledAt()?->format(DATE_ATOM) ?? '',
                    $job->startedAt()?->format(DATE_ATOM) ?? '',
                    $job->finishedAt()?->format(DATE_ATOM) ?? '',
                    $job->durationMs() ?? '',
                    $job->runContext() ?? '',
                    json_encode($job->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($job->result(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $job->errorMessage() ?? '',
                ], ';');
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'system-jobs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
