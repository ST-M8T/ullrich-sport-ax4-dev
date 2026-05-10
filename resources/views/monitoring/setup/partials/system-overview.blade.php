@php
    $status = $status ?? [];
    $health = $status['health'] ?? ['status' => 'unknown', 'checks' => []];
    $configurationSettings = $status['configuration']['settings'] ?? [];
    $configuredCount = collect($configurationSettings)
        ->filter(fn ($setting) => $setting['is_configured'] ?? false)
        ->count();
    $totalSettings = count($configurationSettings);
    $queueSummary = $status['queue'] ?? [];
    $logDirectories = $status['logs']['directories'] ?? [];

    $formatDate = fn ($date) => $date instanceof \DateTimeInterface ? $date->format('d.m.Y H:i:s') : '—';
    $formatBytes = function ($bytes) {
        if (! is_numeric($bytes) || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(count($units) - 1, (int) floor(log((float) $bytes, 1024)));
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power > 0 ? 1 : 0, ',', '.') . ' ' . $units[$power];
    };

    $statusBadgeClass = fn (string $state) => match (strtolower($state)) {
        'ok' => 'bg-success',
        'warn' => 'bg-warning text-dark',
        'fail' => 'bg-danger',
        'skip' => 'bg-secondary',
        default => 'bg-secondary',
    };
@endphp

<div class="stack stack-lg">
    <section class="card">
        <div class="card-body stack stack-sm">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">System-Setup &amp; Monitoring</h2>
                    <p class="mb-0 text-muted">Überblick über Health-Checks, Queue, Logs und wichtige Konfiguration.</p>
                </div>
                <span class="badge {{ $statusBadgeClass($health['status'] ?? 'unknown') }}">
                    {{ strtoupper($health['status'] ?? 'unknown') }}
                </span>
            </div>

            <div class="grid-auto mt-3">
                <div class="data-indicator" data-tone="{{ $configuredCount === $totalSettings ? 'ok' : 'warn' }}">
                    <span class="data-indicator__label">Einstellungen gesetzt</span>
                    <span class="data-indicator__value">{{ $configuredCount }} / {{ $totalSettings }}</span>
                </div>
                <div class="data-indicator" data-tone="info">
                    <span class="data-indicator__label">Queue Connection</span>
                    <span class="data-indicator__value">{{ strtoupper($queueSummary['default_connection'] ?? '—') }}</span>
                </div>
                <div class="data-indicator" data-tone="info">
                    <span class="data-indicator__label">Jobs gesamt</span>
                    <span class="data-indicator__value">{{ $queueSummary['total'] ?? 0 }}</span>
                </div>
                <div class="data-indicator" data-tone="info">
                    <span class="data-indicator__label">Log-Verzeichnisse</span>
                    <span class="data-indicator__value">{{ count($logDirectories) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body stack stack-sm">
            <h3>System Health</h3>

            <div class="row g-3">
                @forelse(($health['checks'] ?? []) as $checkName => $check)
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted text-uppercase small fw-semibold">{{ str_replace('_', ' ', $checkName) }}</div>
                            <div class="d-flex align-items-center gap-2 my-2">
                                <span class="badge {{ $statusBadgeClass($check['status'] ?? 'unknown') }}">
                                    {{ strtoupper($check['status'] ?? 'unknown') }}
                                </span>
                                @if(!empty($check['error']))
                                    <span class="text-danger small">{{ $check['error'] }}</span>
                                @endif
                            </div>
                            <dl class="mb-0 small">
                                @foreach(array_diff_key($check, ['status' => true, 'error' => true]) as $key => $value)
                                    <div class="d-flex justify-content-between">
                                        <dt class="text-muted text-uppercase">{{ str_replace('_', ' ', (string) $key) }}</dt>
                                        <dd class="mb-0 fw-semibold">
                                            @if($value instanceof \DateTimeInterface)
                                                {{ $formatDate($value) }}
                                            @elseif(is_bool($value))
                                                {{ $value ? 'Ja' : 'Nein' }}
                                            @elseif($value === null || $value === '')
                                                —
                                            @else
                                                {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $value }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @empty
                    <div class="col">
                        <div class="border rounded p-3 text-muted small">Keine Health-Checks konfiguriert.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body stack stack-sm">
            <h3>Versionen &amp; Umgebung</h3>
            <div class="row g-3">
                @foreach(($status['versions'] ?? []) as $label => $value)
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted text-uppercase small fw-semibold">{{ str_replace('_', ' ', (string) $label) }}</div>
                            <div class="fw-semibold">
                                @if(is_bool($value))
                                    {{ $value ? 'Ja' : 'Nein' }}
                                @elseif($value === null || $value === '')
                                    —
                                @else
                                    {{ $value }}
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body stack stack-sm">
            <h3>Queue &amp; System-Jobs</h3>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small">Jobs gesamt</div>
                        <div class="display-6 fw-semibold">{{ $queueSummary['total'] ?? 0 }}</div>
                    </div>
                </div>
                @foreach(($queueSummary['counts'] ?? []) as $queueStatus => $count)
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small text-uppercase">{{ $queueStatus }}</div>
                            <div class="h4 mb-0">{{ $count }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Geplant</th>
                        <th scope="col">Start</th>
                        <th scope="col">Ende</th>
                        <th scope="col">Dauer</th>
                        <th scope="col">Fehler</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($queueSummary['recent'] ?? []) as $job)
                        <tr>
                            <td>{{ $job['id'] ?? '—' }}</td>
                            <td>{{ $job['name'] ?? '—' }}</td>
                            <td>{{ $job['status'] ?? '—' }}</td>
                            <td>{{ $formatDate($job['scheduled_at'] ?? null) }}</td>
                            <td>{{ $formatDate($job['started_at'] ?? null) }}</td>
                            <td>{{ $formatDate($job['finished_at'] ?? null) }}</td>
                            <td>
                                @if(isset($job['duration_ms']))
                                    {{ number_format($job['duration_ms'] / 1000, 2, ',', '.') }} s
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $job['error'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted text-center">Keine System-Jobs vorhanden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body stack stack-sm">
            <h3>Konfiguration (Auszug)</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th scope="col">Key</th>
                        <th scope="col">Typ</th>
                        <th scope="col">Wert</th>
                        <th scope="col">Aktualisiert</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(array_slice($configurationSettings, 0, 8) as $setting)
                        <tr>
                            <td><code>{{ $setting['key'] ?? '—' }}</code></td>
                            <td>{{ $setting['value_type'] ?? '—' }}</td>
                            <td>{{ $setting['value'] ?? '—' }}</td>
                            <td>{{ isset($setting['updated_at']) ? $formatDate($setting['updated_at']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center">Keine Einstellungen geladen.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-body stack stack-sm">
            <h3>Logs</h3>
            <div class="row g-3">
                @forelse($logDirectories as $directory)
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">{{ $directory['path'] ?? '—' }}</div>
                            <div class="fw-semibold">{{ $formatBytes($directory['size_bytes'] ?? 0) }}</div>
                            <div class="text-muted small">
                                Letzte Änderung: {{ $formatDate($directory['last_modified_at'] ?? null) }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col">
                        <div class="border rounded p-3 text-muted small">Keine Log-Informationen verfügbar.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</div>

