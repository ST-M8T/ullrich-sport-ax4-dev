@extends('layouts.admin', [
    'pageTitle' => 'System-Jobs',
    'currentSection' => 'monitoring-system-jobs',
    'breadcrumbs' => [
        ['label' => 'Monitoring', 'url' => route('monitoring-system-jobs')],
        ['label' => 'System-Jobs'],
    ],
])

@section('content')
    <div class="stack stack-lg">
        <div>
            <h1 class="mb-1">System-Jobs</h1>
            <p class="text-muted mb-0">Statusübersicht über geplante und laufende Systemaufgaben.</p>
        </div>

        <section class="card">
            <div class="card-body">
                <x-filters.filter-form :action="route('monitoring-system-jobs')" :filters="$filters ?? []">
                    <x-forms.input
                        name="job_name"
                        label="Job Name"
                        type="text"
                        :value="$filters['job_name'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.select
                        name="status"
                        label="Status"
                        :options="collect($statusOptions)->prepend('Alle', '')->all()"
                        :value="$filters['status'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.select
                        name="time_range"
                        label="Zeitraum"
                        :options="collect($timeRanges)->prepend('Gesamter Zeitraum', '')->all()"
                        :value="$filters['time_range'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="from"
                        label="Von"
                        type="datetime-local"
                        :value="$filters['from'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="to"
                        label="Bis"
                        type="datetime-local"
                        :value="$filters['to'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.select
                        name="per_page"
                        label="Pro Seite"
                        :options="array_combine([25, 50, 100, 250], [25, 50, 100, 250])"
                        :value="(string)($filters['per_page'] ?? 25)"
                        col-class="col-md-2"
                    />
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <button type="submit" name="export" value="csv" class="btn btn-outline-primary text-uppercase">CSV-Export</button>
                    </div>
                </x-filters.filter-form>
            </div>
        </section>

    <div class="table-responsive">
        <x-ui.data-table dense striped>
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Name</th>
                <th scope="col">Status</th>
                <th scope="col">Planung</th>
                <th scope="col">Ausführung</th>
                <th scope="col">Dauer (ms)</th>
                <th scope="col">Fehler</th>
                <th scope="col" class="text-end">Mehr</th>
            </tr>
            </thead>
            <tbody>
            @forelse($jobs->items() as $job)
                <tr>
                    @php
                        $statusTone = match ($job->status()) {
                            'completed' => 'success',
                            'running' => 'info',
                            'failed' => 'warning',
                            default => 'info',
                        };
                    @endphp
                    <td>{{ $job->id() }}</td>
                    <td>{{ $job->jobName() }}</td>
                    <td><span class="badge text-uppercase" data-tone="{{ $statusTone }}">{{ $job->status() }}</span></td>
                    <td>{{ $job->scheduledAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>
                        @if($job->startedAt())
                            Start {{ $job->startedAt()?->format('d.m.Y H:i') }}<br>
                        @endif
                        @if($job->finishedAt())
                            Ende {{ $job->finishedAt()?->format('d.m.Y H:i') }}
                        @endif
                        @if(!$job->startedAt() && !$job->finishedAt())
                            —
                        @endif
                    </td>
                    <td>{{ $job->durationMs() ?? '—' }}</td>
                    <td class="small text-muted">
                        {{ \Illuminate\Support\Str::limit($job->errorMessage() ?? '—', 80) }}
                    </td>
                    <td class="text-end">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-monitoring-modal-target="system-job-{{ $job->id() }}"
                                data-monitoring-modal-title="System Job #{{ $job->id() }}">
                            Details
                        </button>
                        <template id="system-job-{{ $job->id() }}">
                            <div class="monitoring-detail">
                                <dl class="monitoring-detail__list">
                                    <div>
                                        <dt>ID</dt>
                                        <dd>{{ $job->id() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Name</dt>
                                        <dd>{{ $job->jobName() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Status</dt>
                                        <dd>{{ $job->status() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Job Typ</dt>
                                        <dd>{{ $job->jobType() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Run Context</dt>
                                        <dd>{{ $job->runContext() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Geplant</dt>
                                        <dd>{{ $job->scheduledAt()?->format('d.m.Y H:i:s') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Gestartet</dt>
                                        <dd>{{ $job->startedAt()?->format('d.m.Y H:i:s') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Beendet</dt>
                                        <dd>{{ $job->finishedAt()?->format('d.m.Y H:i:s') ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Dauer (ms)</dt>
                                        <dd>{{ $job->durationMs() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Payload</dt>
                                        <dd>
                                            @if(!empty($job->payload()))
                                                <pre class="monitoring-pre">{{ json_encode($job->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Result</dt>
                                        <dd>
                                            @if(!empty($job->result()))
                                                <pre class="monitoring-pre">{{ json_encode($job->result(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Fehler</dt>
                                        <dd class="text-break">{{ $job->errorMessage() ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </template>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ui.empty-state
                            title="Keine System-Jobs gefunden"
                            description="Es wurden keine System-Jobs gefunden. Passen Sie Filter und Zeitraum an oder versuchen Sie es später erneut."
                        />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>

    <x-ui.pagination-footer :paginator="$jobs->toLinks('monitoring-system-jobs', request()->query())" label="Einträgen" />

    @include('monitoring.partials.modal')
    </div>
@endsection
