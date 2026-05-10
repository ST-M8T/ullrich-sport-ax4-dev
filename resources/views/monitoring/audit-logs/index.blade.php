@extends('layouts.admin', [
    'pageTitle' => 'Audit-Logs',
    'currentSection' => 'monitoring-audit-logs',
])

@section('content')
    <div class="stack stack-lg">
        <div>
            <h1 class="mb-1">Audit-Logs</h1>
            <p class="text-muted mb-0">Live-Einblicke in Benutzeraktionen und Systemzugriffe.</p>
        </div>

        <section class="card">
            <div class="card-body">
                <x-filters.filter-form :action="route('monitoring-audit-logs')" :filters="$filters ?? []">
                    <x-forms.input
                        name="username"
                        label="Benutzer"
                        type="text"
                        :value="$filters['username'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="action"
                        label="Aktion"
                        type="text"
                        :value="$filters['action'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="ip_address"
                        label="IP-Adresse"
                        type="text"
                        :value="$filters['ip_address'] ?? ''"
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
                <th scope="col">Zeitpunkt</th>
                <th scope="col">Benutzer</th>
                <th scope="col">Aktion</th>
                <th scope="col">IP</th>
                <th scope="col">Kontext</th>
                <th scope="col" class="text-end">Mehr</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs->items() as $log)
                <tr>
                    <td>{{ $log->createdAt()->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $log->actorName() ?? $log->actorId() ?? '—' }}</td>
                    <td>{{ $log->action() }}</td>
                    <td>{{ $log->ipAddress() ?? '—' }}</td>
                    <td class="small text-muted">
                        @php
                            $contextPreview = $log->context();
                            $contextSummary = empty($contextPreview)
                                ? null
                                : \Illuminate\Support\Str::limit(json_encode($contextPreview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80);
                        @endphp
                        {{ $contextSummary ?? '—' }}
                    </td>
                    <td class="text-end">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-monitoring-modal-target="audit-log-{{ $log->id() }}"
                                data-monitoring-modal-title="Audit Log #{{ $log->id() }}">
                            Details
                        </button>
                        <template id="audit-log-{{ $log->id() }}">
                            <div class="monitoring-detail">
                                <dl class="monitoring-detail__list">
                                    <div>
                                        <dt>ID</dt>
                                        <dd>{{ $log->id() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Zeitpunkt</dt>
                                        <dd>{{ $log->createdAt()->format('d.m.Y H:i:s') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Benutzer (ID)</dt>
                                        <dd>{{ $log->actorName() ?? '—' }}{{ $log->actorId() ? ' — '.$log->actorId() : '' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Actor Typ</dt>
                                        <dd>{{ $log->actorType() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Aktion</dt>
                                        <dd>{{ $log->action() }}</dd>
                                    </div>
                                    <div>
                                        <dt>IP-Adresse</dt>
                                        <dd>{{ $log->ipAddress() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>User Agent</dt>
                                        <dd class="text-break">{{ $log->userAgent() ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Kontext</dt>
                                        <dd>
                                            @if(!empty($log->context()))
                                                <pre class="monitoring-pre">{{ json_encode($log->context(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </template>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">Keine Einträge gefunden.</td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>

    <x-ui.pagination-footer :paginator="$logs" label="Einträgen" />

    @include('monitoring.partials.modal')
@endsection
