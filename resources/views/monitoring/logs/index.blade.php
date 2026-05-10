@extends('layouts.admin', [
    'pageTitle' => 'System Logs',
    'currentSection' => 'monitoring-logs',
])

@php
    $files = $files ?? [];
    $entries = $logEntries ?? [];
    $metadata = $metadata ?? ['size' => 0, 'modified_at' => null, 'path' => null, 'limit' => 200];
    $filters = $filters ?? ['file' => '', 'severity' => '', 'from' => '', 'to' => '', 'limit' => 200];
    $errorMessage = $errorMessage ?? null;

    $formatDate = fn ($date) => $date instanceof \DateTimeInterface ? $date->format('d.m.Y H:i:s') : '—';
    $formatBytes = function ($bytes) {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(count($units) - 1, (int) floor(log($bytes, 1024)));
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power > 0 ? 1 : 0, ',', '.').' '.$units[$power];
    };

    $severityOptions = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
@endphp

@section('content')
    <x-ui.page-header>
        <h1 class="mb-1">System Logs</h1>
        <p class="text-muted mb-0">Letzte Logeinträge einsehen, filtern und herunterladen.</p>
        <x-slot:actions>
            <div class="text-end small text-muted">
                <div>Ausgewählte Datei: <span class="fw-semibold">{{ $selectedFile ?? 'laravel.log' }}</span></div>
                <div>Größe: {{ $formatBytes($metadata['size'] ?? 0) }}</div>
                <div>Zuletzt geändert: {{ $formatDate($metadata['modified_at'] ?? null) }}</div>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if($errorMessage)
        <div class="alert alert-danger">{{ $errorMessage }}</div>
    @endif

    <form method="get" class="card card-body mb-4 border-0 shadow-sm">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label for="file" class="form-label small text-uppercase text-muted">Log-Datei</label>
                <select id="file" name="file" class="form-select">
                    @foreach($files as $file)
                        <option value="{{ $file['path'] }}"
                            @selected(($filters['file'] ?: $selectedFile ?? '') === $file['path'])>
                            {{ $file['name'] }} ({{ $formatBytes($file['size']) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="severity" class="form-label small text-uppercase text-muted">Severity</label>
                <select id="severity" name="severity" class="form-select">
                    <option value="">Alle</option>
                    @foreach($severityOptions as $severity)
                        <option value="{{ $severity }}" @selected(strtolower((string) $filters['severity']) === $severity)>
                            {{ ucfirst($severity) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="from" class="form-label small text-uppercase text-muted">Von</label>
                <input type="datetime-local" id="from" name="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="to" class="form-label small text-uppercase text-muted">Bis</label>
                <input type="datetime-local" id="to" name="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-md-3">
                <label for="limit" class="form-label small text-uppercase text-muted">Einträge</label>
                <select id="limit" name="limit" class="form-select">
                    @foreach([50, 100, 200, 300, 500] as $limitOption)
                        <option value="{{ $limitOption }}" @selected((int) $filters['limit'] === $limitOption)>{{ $limitOption }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn btn-primary text-uppercase">FILTERN</button>
            <a href="{{ route('monitoring-logs') }}" class="btn btn-outline-secondary text-uppercase">ZURÜCKSETZEN</a>
            <a href="{{ route('monitoring-logs.download', ['file' => $filters['file'] ?: ($selectedFile ?? null)]) }}" class="btn btn-outline-dark">
                Download
            </a>
        </div>
    </form>

    <div class="table-responsive">
        <x-ui.data-table hover>
            <thead class="table-light">
                <tr>
                    <th scope="col" class="w-25">Zeitpunkt</th>
                    <th scope="col" class="w-10">Severity</th>
                    <th scope="col">Nachricht</th>
                    <th scope="col" class="w-25">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php
                        $levelClass = match ($entry['severity']) {
                            'error', 'critical', 'alert', 'emergency' => 'badge bg-danger',
                            'warning', 'notice' => 'badge bg-warning text-dark',
                            'info' => 'badge bg-info text-dark',
                            default => 'badge bg-secondary',
                        };
                        $stack = $entry['stack'] ?? [];
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $formatDate($entry['datetime'] ?? null) }}</div>
                            <div class="text-muted small">{{ strtoupper($entry['environment'] ?? 'app') }}</div>
                        </td>
                        <td><span class="{{ $levelClass }}">{{ strtoupper($entry['severity']) }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $entry['message'] ?? '' }}</div>
                            @if(! empty($entry['context']))
                                <pre class="bg-light rounded p-2 small mb-0">{{ $entry['context'] }}</pre>
                            @endif
                        </td>
                        <td class="small">
                            @if(! empty($stack))
                                <details>
                                    <summary>Stacktrace ({{ count($stack) }} Zeilen)</summary>
                                    <pre class="bg-light rounded p-2 mt-2">{{ implode(PHP_EOL, $stack) }}</pre>
                                </details>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">Keine Logeinträge für die aktuelle Auswahl.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.data-table>
    </div>
@endsection
