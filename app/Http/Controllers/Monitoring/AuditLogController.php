<?php

namespace App\Http\Controllers\Monitoring;

use App\Application\Monitoring\Queries\ListAuditLogs;
use App\Domain\Monitoring\AuditLogEntry;
use App\Http\Controllers\Monitoring\Concerns\InteractsWithTimeFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AuditLogController
{
    use InteractsWithTimeFilters;

    public function __construct(private readonly ListAuditLogs $listLogs) {}

    public function index(Request $request): View|StreamedResponse
    {
        [$queryFilters, $displayFilters] = $this->prepareFilters($request);
        $perPage = $this->determinePerPage($request);
        $page = max(1, (int) $request->query('page', 1));

        if ($this->shouldExportCsv($request)) {
            $logs = $this->listLogs->export($queryFilters, $this->determineExportLimit($request));

            return $this->exportCsv($logs);
        }

        $logs = ($this->listLogs)($queryFilters, $perPage, $page);

        return view('monitoring.audit-logs.index', [
            'logs' => $logs,
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
            'username' => trim((string) $request->query('username', '')),
            'action' => trim((string) $request->query('action', '')),
            'ip_address' => trim((string) $request->query('ip_address', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'time_range' => trim((string) $request->query('time_range', '')),
        ];

        [$rangeFrom, $rangeTo] = $this->resolveTimeRange($display['time_range'] ?: null);

        $query = [];

        foreach (['username', 'action', 'ip_address'] as $key) {
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
     * @param  iterable<AuditLogEntry>  $logs
     */
    private function exportCsv(iterable $logs): StreamedResponse
    {
        $rows = is_array($logs) ? $logs : iterator_to_array($logs, false);

        $callback = static function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['ID', 'Zeitpunkt', 'Benutzer', 'Aktion', 'IP-Adresse', 'User Agent', 'Kontext'], ';');

            foreach ($rows as $log) {
                fputcsv($handle, [
                    $log->id(),
                    $log->createdAt()->format(DATE_ATOM),
                    $log->actorName() ?? $log->actorId() ?? '',
                    $log->action(),
                    $log->ipAddress() ?? '',
                    $log->userAgent() ?? '',
                    json_encode($log->context(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ';');
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, 'audit-logs.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
