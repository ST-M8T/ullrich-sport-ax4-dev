@extends('layouts.admin', [
    'pageTitle' => 'Fulfillment-Aufträge',
    'currentSection' => 'fulfillment-orders',
])

@php
    /** @var \App\Domain\Fulfillment\Orders\ShipmentOrderPaginationResult $pagination */
@endphp

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Fulfillment-Aufträge</h1>
            <p class="text-muted mb-0">
                Übersicht aller Plenty-Fulfillment-Aufträge mit Filter-, Sync- und Tracking-Aktionen.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('fulfillment-orders') }}" class="btn btn-outline-secondary btn-sm text-uppercase">
                ANSICHT ZURÜCKSETZEN
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <x-filters.filter-tabs
                :tabs="$filterTabs"
                :active-tab="$activeFilter"
                :base-url="$baseRoute"
                :query-params="$query ?? []"
            />

            <x-filters.filter-form :action="$baseRoute" :filters="$query ?? []">
                <input type="hidden" name="filter" value="{{ $activeFilter }}">

                <x-forms.input
                    name="search"
                    label="Suche"
                    type="search"
                    :value="$searchTerm"
                    placeholder="ID, Kunde, E-Mail, Tracking …"
                    autocomplete="off"
                    col-class="col-lg-3 col-md-4"
                />
                <x-forms.input
                    name="sender_code"
                    label="Sender-Code"
                    type="text"
                    :value="$senderCode"
                    maxlength="64"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.input
                    name="destination_country"
                    label="Zielland"
                    type="text"
                    :value="$destinationCountry"
                    maxlength="2"
                    class="text-uppercase"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.select
                    name="is_booked"
                    label="Gebucht"
                    :options="['' => 'Alle', '1' => 'Ja', '0' => 'Nein']"
                    :value="(string)($isBookedFilter ?? '')"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.select
                    name="per_page"
                    label="Pro Seite"
                    :options="array_combine([25, 50, 100], [25, 50, 100])"
                    :value="(string)$perPage"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.select
                    name="sort"
                    label="Sortierung"
                    :options="$sortOptions"
                    :value="$sort"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.select
                    name="dir"
                    label="Reihenfolge"
                    :options="['desc' => '↓', 'asc' => '↑']"
                    :value="$direction"
                    col-class="col-lg-1 col-md-2"
                />
                <x-forms.input
                    name="processed_from"
                    label="Verarbeitet ab"
                    type="date"
                    :value="$processedFrom"
                    col-class="col-lg-2 col-md-3"
                />
                <x-forms.input
                    name="processed_to"
                    label="Verarbeitet bis"
                    type="date"
                    :value="$processedTo"
                    col-class="col-lg-2 col-md-3"
                />
            </x-filters.filter-form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h5 mb-0">Auftragsliste</h2>
                <small class="text-muted">
                    Seite {{ $page }} / {{ $totalPages }} •
                    {{ trans_choice('{0} keine Aufträge|{1} :count Auftrag|[2,*] :count Aufträge', $totalOrders, ['count' => $totalOrders]) }}
                </small>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <form method="post" action="{{ route('fulfillment-orders.sync-visible') }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    @foreach($hiddenFields() as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="scope" value="page">
                    <input type="hidden" name="page" value="{{ $page }}">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        Seite synchronisieren
                    </button>
                </form>

                <form method="post" action="{{ route('fulfillment-orders.sync-visible') }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    @foreach($hiddenFields() as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="scope" value="all">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        Komplette Liste synchronisieren
                    </button>
                </form>

                <form method="post" action="{{ route('fulfillment-orders.sync-booked') }}" class="d-flex gap-2 align-items-center">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                    @foreach($hiddenFields() as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="btn btn-success btn-sm">
                        Gebuchte ins Tracking übertragen
                    </button>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col" class="text-nowrap">#</th>
                    <th scope="col" class="text-nowrap">Auftrag</th>
                    <th scope="col">Kunde</th>
                    <th scope="col">Sender / Land</th>
                    @if($hasTrackingColumn)
                        <th scope="col">Tracking</th>
                    @endif
                    <th scope="col">Rechnungsbetrag</th>
                    <th scope="col">Status</th>
                    <th scope="col">Verarbeitet</th>
                    <th scope="col" class="text-nowrap">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @forelse($orders as $index => $order)
                    @php
                        $rowNumber = ($page - 1) * $perPage + $index + 1;
                        $orderExternalId = $order->externalOrderId();
                        $isExpanded = $expandedId === $orderExternalId;
                        $currentQuery = $buildQuery(['expand' => $isExpanded ? null : $orderExternalId, 'page' => $page]);
                        $expandUrl = $baseRoute . '?' . http_build_query($currentQuery);
                    @endphp
                    <tr class="{{ $isExpanded ? 'table-info' : '' }}">
                        <td class="fw-semibold">
                            {{ $rowNumber }}
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold">#{{ $orderExternalId }}</span>
                                <small class="text-muted">Intern: {{ $order->id()->toInt() }}</small>
                            </div>
                        </td>
                        <td>
                            <div>{{ $order->customerNumber() ?? '—' }}</div>
                            <small class="text-muted">{{ $order->orderType() ?? '—' }}</small>
                            @if($order->contactEmail())
                                <div class="text-muted small">{{ $order->contactEmail() }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $order->senderCode() ?? '—' }}</div>
                            <small class="text-muted text-uppercase">{{ $order->destinationCountry() ?? '??' }}</small>
                        </td>
                        @if($hasTrackingColumn)
                            <td>
                                @if(count($order->trackingNumbers()) > 0)
                                    <ul class="list-unstyled mb-0">
                                        @foreach($order->trackingNumbers() as $tracking)
                                            <li><code>{{ $tracking }}</code></li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @if($order->totalAmount() !== null)
                                {{ number_format($order->totalAmount(), 2, ',', '.') }}&nbsp;{{ $order->currency() }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($order->isBooked())
                                <span class="badge bg-success">Gebucht</span>
                                @if($order->bookedAt())
                                    <div class="small text-muted mt-1">
                                        {{ $order->bookedAt()?->format('d.m.Y H:i') }}<br>
                                        {{ $order->bookedBy() ?? 'Automatisch' }}
                                    </div>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark">Offen</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $order->processedAt()?->format('d.m.Y H:i') ?? '—' }}</div>
                            <small class="text-muted">Aktualisiert {{ $order->updatedAt()->format('d.m.Y H:i') }}</small>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('fulfillment-orders.show', $order->id()->toInt()) }}" class="btn btn-outline-secondary btn-sm">
                                    Details
                                </a>
                                <a href="{{ $expandUrl }}" class="btn btn-outline-secondary btn-sm">
                                    {{ $isExpanded ? 'Details schließen' : 'Positionen' }}
                                </a>
                                @if(!$order->isBooked())
                                    <form method="post" action="{{ route('fulfillment-orders.book', $order->id()->toInt()) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                        <button type="submit" class="btn btn-primary btn-sm">Buchen</button>
                                    </form>
                                @else
                                    <form method="post" action="{{ route('fulfillment-orders.transfer', $order->id()->toInt()) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            Tracking-Transfer
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if($isExpanded && $expandedOrder)
                        <tr class="bg-body-tertiary">
                            <td colspan="{{ $hasTrackingColumn ? 9 : 8 }}">
                                <div class="p-3 border rounded bg-white">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div>
                                            <h3 class="h6 mb-1">Positionen zu Auftrag #{{ $expandedOrder->externalOrderId() }}</h3>
                                            <small class="text-muted">
                                                Kunde: {{ $expandedOrder->customerNumber() ?? '—' }},
                                                Sender: {{ $expandedOrder->senderCode() ?? '—' }}
                                            </small>
                                        </div>
                                        <div class="fw-semibold">
                                            Gesamtgewicht: {{ number_format($expandedWeight, 2, ',', '.') }} kg
                                        </div>
                                    </div>

                                    <div class="table-responsive mb-3">
                                        <x-ui.data-table dense striped>
                                            <thead>
                                            <tr>
                                                <th scope="col">SKU</th>
                                                <th scope="col">Beschreibung</th>
                                                <th scope="col" class="text-end">Menge</th>
                                                <th scope="col" class="text-end">Gewicht (kg)</th>
                                                <th scope="col">Assembly</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse($expandedItems as $item)
                                                <tr>
                                                    <td><code>{{ $item['sku'] ?? '—' }}</code></td>
                                                    <td>{{ $item['description'] ?? '—' }}</td>
                                                    <td class="text-end">{{ $item['quantity'] ?? 0 }}</td>
                                                    <td class="text-end">
                                                        {{ $item['weight'] !== null ? number_format($item['weight'], 2, ',', '.') : '—' }}
                                                    </td>
                                                    <td>{{ !empty($item['is_assembly']) ? 'Ja' : 'Nein' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">Keine Positionen vorhanden.</td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </x-ui.data-table>
                                    </div>

                                    <div>
                                        <h4 class="h6">Pakete</h4>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0">
                                                <thead>
                                                <tr>
                                                    <th scope="col">Referenz</th>
                                                    <th scope="col" class="text-end">Menge</th>
                                                    <th scope="col" class="text-end">Gewicht (kg)</th>
                                                    <th scope="col">Maße (mm)</th>
                                                    <th scope="col" class="text-end">Truck Slots</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @forelse($expandedPackages as $package)
                                                    <tr>
                                                        <td>{{ $package['reference'] ?? '—' }}</td>
                                                        <td class="text-end">{{ $package['quantity'] ?? 0 }}</td>
                                                        <td class="text-end">
                                                            {{ $package['weight'] !== null ? number_format($package['weight'], 2, ',', '.') : '—' }}
                                                        </td>
                                                        <td>
                                                            @php
                                                                $dimensions = $package['dimensions'] ?? [];
                                                            @endphp
                                                            @if($dimensions[0] ?? null)
                                                                {{ $dimensions[0] }} × {{ $dimensions[1] ?? '—' }} × {{ $dimensions[2] ?? '—' }}
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end">{{ $package['truck_slots'] ?? '—' }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">Keine Paketinformationen vorhanden.</td>
                                                    </tr>
                                                @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ $hasTrackingColumn ? 9 : 8 }}" class="text-center text-muted py-4">
                            Keine Aufträge für die aktuelle Auswahl gefunden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('fulfillment-orders.sync-manual') }}" class="row g-3 align-items-end">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                @foreach($hiddenFields() as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <div class="col-md-3">
                    <label for="manual_order_id" class="form-label">Auftrags-ID</label>
                    <input type="number" min="1" class="form-control" id="manual_order_id" name="manual_order_id" required>
                </div>
                <div class="col-md-3">
                    <label for="manual_tracking" class="form-label">Trackingnummer (optional)</label>
                    <input type="text" class="form-control" id="manual_tracking" name="manual_tracking" maxlength="191">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="manual_sync">Direkt synchronisieren</label>
                    <select name="manual_sync" id="manual_sync" class="form-select">
                        <option value="0" selected>Später</option>
                        <option value="1">Sofort synchronisieren</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Nachtragen &amp; synchronisieren</button>
                </div>
            </form>
        </div>
    </div>

    <nav aria-label="Pagination" class="mt-3">
        <ul class="pagination">
            <li class="page-item @if($page === 1) disabled @endif">
                <a class="page-link" href="{{ $baseRoute . '?' . http_build_query($buildQuery(['page' => max(1, $page - 1)])) }}">Zurück</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Seite {{ $page }} / {{ $totalPages }}</span>
            </li>
            <li class="page-item @if(!$pagination->hasMorePages()) disabled @endif">
                <a class="page-link" href="{{ $baseRoute . '?' . http_build_query($buildQuery(['page' => $page + 1])) }}">Weiter</a>
            </li>
        </ul>
    </nav>
@endsection
