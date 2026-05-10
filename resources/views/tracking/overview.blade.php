@extends('layouts.admin', [
    'pageTitle' => 'Tracking-Übersicht',
    'currentSection' => 'tracking-overview',
])

@php
    $jobShowTemplate = route('tracking-jobs.show', ['job' => '__JOB__']);
    $jobRetryTemplate = route('tracking-jobs.retry', ['job' => '__JOB__']);
    $jobFailTemplate = route('tracking-jobs.fail', ['job' => '__JOB__']);
    $alertShowTemplate = route('tracking-alerts.show', ['alert' => '__ALERT__']);
    $alertAckTemplate = route('tracking-alerts.acknowledge', ['alert' => '__ALERT__']);
@endphp

@section('content')
    <div
        data-tracking-overview
        class="d-flex flex-column gap-4"
        data-initial-tab="{{ $initialTab ?? 'jobs' }}"
        data-job-show-template="{{ $jobShowTemplate }}"
        data-job-retry-template="{{ $jobRetryTemplate }}"
        data-job-fail-template="{{ $jobFailTemplate }}"
        data-alert-show-template="{{ $alertShowTemplate }}"
        data-alert-ack-template="{{ $alertAckTemplate }}"
    >
        <header>
            <h1 class="mb-1">Tracking-Übersicht</h1>
            <p class="text-muted mb-0">Monitoring für Tracking-Jobs und Alerts.</p>
        </header>

        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-tab-button="jobs" aria-pressed="true">
                Jobs
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" data-tab-button="alerts" aria-pressed="false">
                Alerts
            </button>
        </div>

        <section class="card" data-tab-panel="jobs">
            <div class="card-body">
            <header class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-3">
                <div>
                    <h2 class="h4 mb-1">Tracking Jobs</h2>
                    <p class="text-muted mb-0">Geplante und ausgeführte Tracking-Tasks.</p>
                </div>
                <x-filters.filter-form :action="route('tracking-overview')" :filters="$jobFilters ?? []">
                    <input type="hidden" name="tab" value="jobs">
                    <x-forms.input
                        name="job_type"
                        label="Job-Typ"
                        type="text"
                        :value="$jobFilters['job_type'] ?? ''"
                        placeholder="job-sync"
                        col-class="col-auto"
                    />
                    <x-forms.select
                        name="job_status"
                        label="Status"
                        :options="$jobStatusOptions"
                        :value="$jobFilters['status'] ?? ''"
                        col-class="col-auto"
                    />
                </x-filters.filter-form>
            </header>

            <div class="table-responsive">
                <x-ui.data-table dense striped>
                    <thead>
                    <tr>
                        <th scope="col">Job</th>
                        <th scope="col">Status</th>
                        <th scope="col">Termine</th>
                        <th scope="col">Versuche</th>
                        <th scope="col">Fehler</th>
                        <th scope="col">Daten</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($jobs as $job)
                        @php
                            $status = $job->status();
                            $statusClass = match ($status) {
                                'completed' => 'bg-success',
                                'failed' => 'bg-danger',
                                'running' => 'bg-info',
                                default => 'bg-secondary',
                            };
                            $payloadJson = json_encode($job->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            $resultJson = json_encode($job->result(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        @endphp
                        <tr data-job-row="{{ $job->id()->toInt() }}">
                            <td>
                                <strong>{{ $job->jobType() }}</strong><br>
                                <small class="text-muted">#{{ (string) $job->id() }}</small>
                            </td>
                            <td data-job-status>
                                <span class="badge {{ $statusClass }} text-uppercase">{{ $status }}</span>
                            </td>
                            <td class="small" data-job-timestamps>
                                @if($job->scheduledAt())
                                    <div><strong>Scheduled:</strong> {{ $job->scheduledAt()?->format('d.m.Y H:i') }}</div>
                                @endif
                                @if($job->startedAt())
                                    <div><strong>Started:</strong> {{ $job->startedAt()?->format('d.m.Y H:i') }}</div>
                                @endif
                                @if($job->finishedAt())
                                    <div><strong>Finished:</strong> {{ $job->finishedAt()?->format('d.m.Y H:i') }}</div>
                                @endif
                                <div><strong>Created:</strong> {{ $job->createdAt()->format('d.m.Y H:i') }}</div>
                            </td>
                            <td data-job-attempt>
                                {{ $job->attempt() }}
                            </td>
                            <td data-job-error>
                                {{ $job->lastError() ?? '—' }}
                            </td>
                            <td class="small" data-job-data>
                                @if($payloadJson && $payloadJson !== '[]')
                                    <details class="mb-1">
                                        <summary>Payload</summary>
                                        <pre class="mb-0">{{ $payloadJson }}</pre>
                                    </details>
                                @else
                                    <span class="text-muted">Payload leer</span>
                                @endif
                                @if($resultJson && $resultJson !== '[]')
                                    <details>
                                        <summary>Result</summary>
                                        <pre class="mb-0">{{ $resultJson }}</pre>
                                    </details>
                                @else
                                    <span class="text-muted">Result leer</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-primary btn-sm js-open-job-modal" data-job-id="{{ $job->id()->toInt() }}">
                                    Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Keine Jobs gefunden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
            </div>
        </section>

        <section class="card" data-tab-panel="alerts" hidden>
            <div class="card-body">
            <header class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-3">
                <div>
                    <h2 class="h4 mb-1">Tracking Alerts</h2>
                    <p class="text-muted mb-0">Benachrichtigungen zu Tracking-Problemen.</p>
                </div>
                <x-filters.filter-form :action="route('tracking-overview')" :filters="$alertFilters ?? []">
                    <input type="hidden" name="tab" value="alerts">
                    <x-forms.input
                        name="alert_type"
                        label="Alert-Typ"
                        type="text"
                        :value="$alertFilters['alert_type'] ?? ''"
                        placeholder="alert-type"
                        col-class="col-auto"
                    />
                    <x-forms.select
                        name="alert_severity"
                        label="Severity"
                        :options="$alertSeverityOptions"
                        :value="$alertFilters['severity'] ?? ''"
                        col-class="col-auto"
                    />
                    <x-forms.input
                        name="alert_channel"
                        label="Channel"
                        type="text"
                        :value="$alertFilters['channel'] ?? ''"
                        placeholder="mail"
                        col-class="col-auto"
                    />
                    <x-forms.select
                        name="alert_is_acknowledged"
                        label="Bestätigt"
                        :options="$ackOptions"
                        :value="(string)(isset($alertFilters['is_acknowledged']) ? ((int) $alertFilters['is_acknowledged']) : '')"
                        col-class="col-auto"
                    />
                </x-filters.filter-form>
            </header>

            <div class="table-responsive">
                <x-ui.data-table dense striped>
                    <thead>
                    <tr>
                        <th scope="col">Alert</th>
                        <th scope="col">Severity</th>
                        <th scope="col">Channel</th>
                        <th scope="col">Nachricht</th>
                        <th scope="col">Status</th>
                        <th scope="col">Metadata</th>
                        <th scope="col" class="text-end">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($alerts as $alert)
                        @php
                            $severity = $alert->severity();
                            $severityClass = match ($severity) {
                                'critical' => 'bg-danger',
                                'error' => 'bg-danger',
                                'warning' => 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            $metaJson = json_encode($alert->metadata(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $alert->alertType() }}</strong><br>
                                <small class="text-muted">#{{ (string) $alert->id() }}</small>
                            </td>
                            <td data-alert-severity>
                                <span class="badge {{ $severityClass }} text-uppercase">{{ $severity }}</span>
                            </td>
                            <td>{{ $alert->channel() ?? '—' }}</td>
                            <td class="small">{{ $alert->message() }}</td>
                            <td class="small" data-alert-status>
                                <div><strong>Erstellt:</strong> {{ $alert->createdAt()->format('d.m.Y H:i') }}</div>
                                @if($alert->sentAt())
                                    <div><strong>Gesendet:</strong> {{ $alert->sentAt()?->format('d.m.Y H:i') }}</div>
                                @endif
                                @if($alert->acknowledgedAt())
                                    <div><strong>Bestätigt:</strong> {{ $alert->acknowledgedAt()?->format('d.m.Y H:i') }}</div>
                                @else
                                    <span class="badge bg-warning text-dark mt-1">Offen</span>
                                @endif
                            </td>
                            <td class="small" data-alert-metadata>
                                @if($metaJson && $metaJson !== '[]')
                                    <details>
                                        <summary>Details</summary>
                                        <pre class="mb-0">{{ $metaJson }}</pre>
                                    </details>
                                @else
                                    <span class="text-muted">Keine Daten</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-outline-primary btn-sm js-open-alert-modal" data-alert-id="{{ $alert->id()->toInt() }}">
                                    Details
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Keine Alerts vorhanden.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
            </div>
        </section>
    </div>

    <div class="app-modal" data-modal="job" aria-hidden="true">
        <div class="app-modal__dialog modal-dialog">
            <div class="app-modal__header">
                <div>
                    <h2 class="app-modal__title">Job-Details</h2>
                </div>
                <button type="button" class="btn-close app-modal__close" data-modal-close aria-label="Schließen"></button>
            </div>
            <div class="app-modal__body">
                <div class="modal-status alert d-none" data-modal-status></div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Übersicht</h3>
                    <div data-job-modal-details class="modal-details"></div>
                </div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Historie</h3>
                    <div data-job-modal-history class="modal-history text-muted">Keine weiteren Einträge.</div>
                </div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Payload</h3>
                    <pre data-job-modal-payload class="modal-json text-muted">–</pre>
                </div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Result</h3>
                    <pre data-job-modal-result class="modal-json text-muted">–</pre>
                </div>
                <div class="modal-section">
                    <label class="form-label fw-semibold small text-muted text-uppercase">Fehlergrund (optional)</label>
                    <textarea class="form-control form-control-sm" rows="2" data-job-fail-reason placeholder="Grund für manuelles Failen..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline-secondary" data-modal-close>Schließen</button>
                    <button type="button" class="btn btn-danger js-job-fail">Als failed markieren</button>
                    <button type="button" class="btn btn-primary js-job-retry">Retry</button>
                </div>
            </div>
        </div>
        <div class="app-modal__backdrop" data-modal-close></div>
    </div>

    <div class="app-modal" data-modal="alert" aria-hidden="true">
        <div class="app-modal__dialog modal-dialog">
            <div class="app-modal__header">
                <div>
                    <h2 class="app-modal__title">Alert-Details</h2>
                </div>
                <button type="button" class="btn-close app-modal__close" data-modal-close aria-label="Schließen"></button>
            </div>
            <div class="app-modal__body">
                <div class="modal-status alert d-none" data-modal-status></div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Übersicht</h3>
                    <div data-alert-modal-details class="modal-details"></div>
                </div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Ähnliche Alerts</h3>
                    <div data-alert-modal-related class="modal-history text-muted">Keine weiteren Alerts.</div>
                </div>
                <div class="modal-section">
                    <h3 class="modal-subtitle">Metadata</h3>
                    <pre data-alert-modal-metadata class="modal-json text-muted">–</pre>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline-secondary" data-modal-close>Schließen</button>
                    <button type="button" class="btn btn-success js-alert-ack">Acknowledge</button>
                </div>
            </div>
        </div>
        <div class="app-modal__backdrop" data-modal-close></div>
    </div>
@endsection
