@extends('layouts.admin', [
    'pageTitle' => 'Domain-Events',
    'currentSection' => 'monitoring-domain-events',
])

@section('content')
    <div class="stack stack-lg">
        <div>
            <h1 class="mb-1">Domain-Events</h1>
            <p class="text-muted mb-0">Timeline aller publizierten Domain-Events mit Payload und Metadaten.</p>
        </div>

        <section class="card">
            <div class="card-body">
                <x-filters.filter-form :action="route('monitoring-domain-events')" :filters="$filters ?? []">
                    <x-forms.input
                        name="event_name"
                        label="Event"
                        type="text"
                        :value="$filters['event_name'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="aggregate_type"
                        label="Aggregate Typ"
                        type="text"
                        :value="$filters['aggregate_type'] ?? ''"
                        col-class="col-md-2"
                    />
                    <x-forms.input
                        name="aggregate_id"
                        label="Aggregate ID"
                        type="text"
                        :value="$filters['aggregate_id'] ?? ''"
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
                <th scope="col">Occurred</th>
                <th scope="col">Event</th>
                <th scope="col">Aggregate</th>
                <th scope="col">Payload</th>
                <th scope="col">Metadaten</th>
                <th scope="col" class="text-end">Mehr</th>
            </tr>
            </thead>
            <tbody>
            @forelse($events->items() as $event)
                <tr>
                    <td>{{ $event->occurredAt()->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $event->eventName() }}</td>
                    <td>{{ $event->aggregateType() }} / {{ $event->aggregateId() }}</td>
                    <td class="small text-muted">
                        @php
                            $payload = $event->payload();
                            $payloadSummary = empty($payload)
                                ? null
                                : \Illuminate\Support\Str::limit(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80);
                        @endphp
                        {{ $payloadSummary ?? '—' }}
                    </td>
                    <td class="small text-muted">
                        @php
                            $metadata = $event->metadata();
                            $metadataSummary = empty($metadata)
                                ? null
                                : \Illuminate\Support\Str::limit(json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 80);
                        @endphp
                        {{ $metadataSummary ?? '—' }}
                    </td>
                    <td class="text-end">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-monitoring-modal-target="domain-event-{{ $event->id()->toString() }}"
                                data-monitoring-modal-title="Domain Event {{ $event->eventName() }}">
                            Details
                        </button>
                        <template id="domain-event-{{ $event->id()->toString() }}">
                            <div class="monitoring-detail">
                                <dl class="monitoring-detail__list">
                                    <div>
                                        <dt>ID</dt>
                                        <dd>{{ $event->id()->toString() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Occurred</dt>
                                        <dd>{{ $event->occurredAt()->format('d.m.Y H:i:s') }}</dd>
                                    </div>
                                    <div>
                                        <dt>Event</dt>
                                        <dd>{{ $event->eventName() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Aggregate</dt>
                                        <dd>{{ $event->aggregateType() }} / {{ $event->aggregateId() }}</dd>
                                    </div>
                                    <div>
                                        <dt>Payload</dt>
                                        <dd>
                                            @if(!empty($event->payload()))
                                                <pre class="monitoring-pre">{!! json_encode($event->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</pre>
                                            @else
                                                <span>—</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Metadaten</dt>
                                        <dd>
                                            @if(!empty($event->metadata()))
                                                <pre class="monitoring-pre">{!! json_encode($event->metadata(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</pre>
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
                    <td colspan="6" class="text-center text-muted">Keine Domain-Events vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>

    <x-ui.pagination-footer :paginator="$events" label="Einträgen" />

    @include('monitoring.partials.modal')
@endsection
