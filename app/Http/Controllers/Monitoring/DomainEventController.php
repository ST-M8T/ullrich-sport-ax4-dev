<?php

namespace App\Http\Controllers\Monitoring;

use App\Application\Monitoring\Queries\ListDomainEvents;
use App\Domain\Monitoring\DomainEventRecord;
use App\Http\Controllers\Monitoring\Concerns\InteractsWithTimeFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DomainEventController
{
    use InteractsWithTimeFilters;

    public function __construct(private readonly ListDomainEvents $listEvents) {}

    public function index(Request $request): View|StreamedResponse
    {
        [$queryFilters, $displayFilters] = $this->prepareFilters($request);
        $perPage = $this->determinePerPage($request);
        $page = max(1, (int) $request->query('page', 1));

        if ($this->shouldExportCsv($request)) {
            $events = $this->listEvents->export($queryFilters, $this->determineExportLimit($request));

            return $this->exportCsv($events);
        }

        $events = ($this->listEvents)($queryFilters, $perPage, $page);

        return view('monitoring.domain-events.index', [
            'events' => $events,
            'filters' => array_merge($displayFilters, [
                'per_page' => $perPage,
            ]),
            'timeRanges' => $this->timeRangeOptions(),
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
            'event_name' => trim((string) $request->query('event_name', '')),
            'aggregate_type' => trim((string) $request->query('aggregate_type', '')),
            'aggregate_id' => trim((string) $request->query('aggregate_id', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'time_range' => trim((string) $request->query('time_range', '')),
        ];

        [$rangeFrom, $rangeTo] = $this->resolveTimeRange($display['time_range'] ?: null);

        $query = [];

        foreach (['event_name', 'aggregate_type', 'aggregate_id'] as $key) {
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
     * @param  iterable<DomainEventRecord>  $events
     */
    private function exportCsv(iterable $events): StreamedResponse
    {
        $rows = is_array($events) ? $events : iterator_to_array($events, false);

        $callback = static function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'ID',
                'Event',
                'Aggregate Typ',
                'Aggregate ID',
                'Occurred At',
                'Created At',
                'Payload',
                'Metadata',
            ], ';');

            foreach ($rows as $event) {
                fputcsv($handle, [
                    $event->id()->toString(),
                    $event->eventName(),
                    $event->aggregateType(),
                    $event->aggregateId(),
                    $event->occurredAt()->format(DATE_ATOM),
                    $event->createdAt()->format(DATE_ATOM),
                    json_encode($event->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($event->metadata(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ';');
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'domain-events.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
