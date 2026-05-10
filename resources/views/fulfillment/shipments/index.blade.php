@extends('layouts.admin', [
    'pageTitle' => 'Fulfillment-Sendungen',
    'currentSection' => 'fulfillment-shipments',
])


@section('content')
    <h1 class="mb-4">Sendungsverfolgung</h1>

    <div class="card mb-4">
        <div class="card-body">
            <x-filters.filter-form :action="route('fulfillment-shipments')" :filters="$filters ?? []">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <x-forms.input
                    name="carrier"
                    label="Carrier"
                    type="text"
                    :value="$filters['carrier'] ?? ''"
                    col-class="col-md-2"
                />
                <x-forms.input
                    name="status"
                    label="Status-Code"
                    type="text"
                    :value="$filters['status'] ?? ''"
                    col-class="col-md-2"
                />
                <x-forms.input
                    name="date_from"
                    label="Von"
                    type="datetime-local"
                    :value="isset($filters['date_from']) ? \Illuminate\Support\Str::of($filters['date_from'])->replace(' ', 'T') : ''"
                    col-class="col-md-3"
                />
                <x-forms.input
                    name="date_to"
                    label="Bis"
                    type="datetime-local"
                    :value="isset($filters['date_to']) ? \Illuminate\Support\Str::of($filters['date_to'])->replace(' ', 'T') : ''"
                    col-class="col-md-3"
                />
            </x-filters.filter-form>
        </div>
    </div>

    <x-tabs
        :tabs="$tabs ?? []"
        :active-tab="$activeTab"
        :base-url="$baseUrl ?? request()->url()"
        tab-param="tab"
        aria-label="Sendungsverfolgung-Bereiche"
        class="mb-3"
    />

    @if($activeTab === 'overview')
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Sendungen</h2>
            </div>
            <div class="table-responsive">
                <x-ui.data-table striped hover>
                <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Carrier</th>
                    <th scope="col">Tracking</th>
                    <th scope="col">Status</th>
                    <th scope="col">Letztes Event</th>
                    <th scope="col">Events</th>
                    <th scope="col">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @forelse($shipments as $shipment)
                    <tr>
                        <td>
                            <strong>#{{ $shipment['id'] }}</strong><br>
                            <small class="text-muted">Erstellt {{ $formatDate($shipment['created_at'] ?? null) }}</small>
                        </td>
                        <td>
                            {{ $shipment['carrier_code'] }}<br>
                            <small class="text-muted">
                                Profil: {{ $shipment['shipping_profile_id'] ?? '—' }}
                            </small>
                        </td>
                        <td>
                            <code>{{ $shipment['tracking_number'] }}</code><br>
                            <small class="text-muted">Versuche {{ $shipment['failed_attempts'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary" aria-label="Status: {{ $shipment['status_code'] ?? 'unbekannt' }}">{{ $shipment['status_code'] ?? '—' }}</span><br>
                            <small class="text-muted">{{ $shipment['status_description'] ?? 'Keine Beschreibung' }}</small>
                        </td>
                        <td>
                            {{ $formatDate($shipment['last_event_at'] ?? null) }}<br>
                            <small class="text-muted">Nächster Sync {{ $formatDate($shipment['next_sync_after'] ?? null) }}</small>
                        </td>
                        <td>
                            {{ count($shipment['events']) }} Events<br>
                            <small class="text-muted">
                                Geliefert: {{ $shipment['is_delivered'] ? 'Ja' : 'Nein' }}
                            </small>
                        </td>
                        <td>
                            <form method="post" action="{{ route('fulfillment-shipments.sync', $shipment['id']) }}" class="d-flex flex-column gap-2">
                                @csrf
                                <input type="hidden" name="tab" value="{{ $activeTab }}">
                                <input type="hidden" name="carrier" value="{{ $filters['carrier'] ?? '' }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="Notiz">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Sync auslösen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">Keine Sendungen gefunden.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
            </div>
        </div>

        @if($pagination)
            <nav aria-label="Pagination" class="mt-3">
                <ul class="pagination">
                    <li class="page-item @if($pagination->page === 1) disabled @endif">
                        <a class="page-link"
                           href="{{ request()->fullUrlWithQuery(['page' => max(1, $pagination->page - 1)]) }}">Zurück</a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link">Seite {{ $pagination->page }} / {{ $pagination->totalPages() }}</span>
                    </li>
                    <li class="page-item @if(!$pagination->hasMorePages()) disabled @endif">
                        <a class="page-link"
                           href="{{ request()->fullUrlWithQuery(['page' => $pagination->page + 1]) }}">Weiter</a>
                    </li>
                </ul>
            </nav>
        @endif
    @endif

    @if($activeTab === 'events')
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Events</h2>
            </div>
            <div class="table-responsive">
                <x-ui.data-table hover>
                <thead>
                <tr>
                    <th scope="col">Occurred</th>
                    <th scope="col">Sendung</th>
                    <th scope="col">Event-Code</th>
                    <th scope="col">Status</th>
                    <th scope="col">Beschreibung</th>
                    <th scope="col">Ort</th>
                </tr>
                </thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $formatDate($event['occurred_at'] ?? null) }}</td>
                        <td>
                            <div><strong>#{{ $event['shipment']['id'] ?? '?' }}</strong></div>
                            <div><code>{{ $event['shipment']['tracking_number'] ?? '' }}</code></div>
                        </td>
                        <td>{{ $event['event_code'] ?? '—' }}</td>
                        <td>{{ $event['status'] ?? '—' }}</td>
                        <td>
                            {{ $event['description'] ?? '—' }}<br>
                            <small class="text-muted">
                                Payload: {{ json_encode($event['payload'] ?? [], JSON_UNESCAPED_UNICODE) }}
                            </small>
                        </td>
                        <td>
                            {{ $event['facility'] ?? '—' }}<br>
                            <small class="text-muted">{{ trim(($event['city'] ?? '') . ' ' . ($event['country'] ?? '')) }}</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted">Keine Events für die Auswahl.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
            </div>
        </div>
    @endif

    @if($activeTab === 'sync-history')
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Sync-Historie</h2>
            </div>
            <div class="table-responsive">
                <x-ui.data-table hover>
                <thead>
                <tr>
                    <th scope="col">Occurred</th>
                    <th scope="col">Sendung</th>
                    <th scope="col">Initiator</th>
                    <th scope="col">Notiz</th>
                </tr>
                </thead>
                <tbody>
                @forelse($syncHistory as $event)
                    @php
                        $payload = $event['payload'] ?? [];
                        $initiator = $payload['initiator'] ?? 'Unbekannt';
                        $note = $payload['note'] ?? ($event['description'] ?? '');
                    @endphp
                    <tr>
                        <td>{{ $formatDate($event['occurred_at'] ?? null) }}</td>
                        <td>
                            <div><strong>#{{ $event['shipment']['id'] ?? '?' }}</strong></div>
                            <div><code>{{ $event['shipment']['tracking_number'] ?? '' }}</code></div>
                        </td>
                        <td>{{ $initiator }}</td>
                        <td>{{ $note }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted">Noch keine manuellen Syncs ausgelöst.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
            </div>
        </div>
    @endif
@endsection
