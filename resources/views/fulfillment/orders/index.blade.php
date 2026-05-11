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

                <button type="button" class="btn btn-primary btn-sm" id="bulk-dhl-book-btn" disabled>
                    <i class="bi bi-truck"></i> DHL bulk buchen
                </button>
                <button type="button" class="btn btn-danger btn-sm" id="bulk-dhl-cancel-btn" disabled>
                    <i class="bi bi-x-circle"></i> Ausgewählte stornieren
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th scope="col" class="text-nowrap">
                        <input type="checkbox" id="select-all-orders" class="form-check-input" title="Alle auswählen">
                    </th>
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
                    <tr class="{{ $isExpanded ? 'table-info' : '' }}" data-order-id="{{ $order->id()->toInt() }}">
                        <td>
                            @if($order->dhlShipmentId() && !$order->dhlCancelledAt())
                                <input type="checkbox" class="form-check-input order-checkbox" name="order_ids[]" value="{{ $order->id()->toInt() }}">
                            @endif
                        </td>
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

    <x-dhl.bulk-booking-modal />

    {{-- Bulk Cancellation Modal --}}
    <x-ui.modal title="DHL-Sendungen stornieren" id="dhl-bulk-cancel-modal" size="md">
        <form id="dhl-bulk-cancel-form" method="post">
            @csrf
            <input type="hidden" name="action" value="bulk-cancel">
            <input type="hidden" name="order_ids" id="bulk-cancel-order-ids">
            <div class="mb-3">
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span id="bulk-cancel-count">0</span> Aufträge zum Stornieren ausgewählt
                </div>
            </div>
            <div class="mb-3">
                <label for="bulk_cancel_reason" class="form-label">Stornierungsgrund (optional)</label>
                <textarea class="form-control" id="bulk_cancel_reason" name="reason" rows="3" maxlength="500" placeholder="z.B. Kunde hat storniert, doppelte Buchung, etc."></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="submit" class="btn btn-danger" id="bulk-cancel-submit-btn">
                    <span class="spinner-border spinner-border-sm d-none" id="bulk-cancel-spinner"></span>
                    <span id="bulk-cancel-submit-text">Stornieren</span>
                </button>
            </div>
        </form>
    </x-ui.modal>

    @section('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAllCheckbox = document.getElementById('select-all-orders');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        const bulkDhlBtn = document.getElementById('bulk-dhl-book-btn');
        const bulkModal = document.getElementById('dhl-bulk-booking-modal');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                orderCheckboxes.forEach(function (cb) {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateBulkButtonState();
            });
        }

        // Individual checkbox change
        orderCheckboxes.forEach(function (cb) {
            cb.addEventListener('change', function () {
                updateBulkButtonState();
                // Update "select all" checkbox state
                if (selectAllCheckbox) {
                    const allChecked = Array.from(orderCheckboxes).every(function (c) { return c.checked; });
                    const noneChecked = Array.from(orderCheckboxes).every(function (c) { return !c.checked; });
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = !allChecked && !noneChecked;
                }
            });
        });

        function updateBulkButtonState() {
            const checked = document.querySelectorAll('.order-checkbox:checked');
            const count = checked.length;
            bulkDhlBtn.disabled = count === 0;
            bulkDhlBtn.innerHTML = '<i class="bi bi-truck"></i> DHL bulk buchen' + (count > 0 ? ' (' + count + ')' : '');

            const bulkCancelBtn = document.getElementById('bulk-dhl-cancel-btn');
            if (bulkCancelBtn) {
                bulkCancelBtn.disabled = count === 0;
                bulkCancelBtn.innerHTML = '<i class="bi bi-x-circle"></i> Ausgewählte stornieren' + (count > 0 ? ' (' + count + ')' : '');
            }
        }

        // Open bulk cancel modal when button clicked
        const bulkCancelBtn = document.getElementById('bulk-dhl-cancel-btn');
        const bulkCancelModal = document.getElementById('dhl-bulk-cancel-modal');
        if (bulkCancelBtn && bulkCancelModal) {
            bulkCancelBtn.addEventListener('click', function () {
                const checked = document.querySelectorAll('.order-checkbox:checked');
                const orderIds = Array.from(checked).map(function (cb) { return parseInt(cb.value, 10); });

                if (orderIds.length === 0) {
                    return;
                }

                document.getElementById('bulk-cancel-order-ids').value = JSON.stringify(orderIds);
                document.getElementById('bulk-cancel-count').textContent = orderIds.length;
            });
        }

        // Bulk cancel form submission
        document.getElementById('dhl-bulk-cancel-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = document.getElementById('bulk-cancel-spinner');
            const submitText = document.getElementById('bulk-cancel-submit-text');

            // Disable button
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            submitText.textContent = 'Wird storniert…';

            const orderIds = JSON.parse(document.getElementById('bulk-cancel-order-ids').value || '[]');
            const reason = document.getElementById('bulk_cancel_reason').value;

            fetch('/api/admin/dhl/bulk-cancel', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + getCsrfToken(),
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    order_ids: orderIds,
                    reason: reason,
                })
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.errors && data.errors.length > 0) {
                    alert('Fehler: ' + (data.errors[0].detail || 'Unbekannt'));
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                    submitText.textContent = 'Stornieren';
                    return;
                }

                const attrs = data.data?.attributes || {};
                let message = 'Erfolgreich storniert: ' + (attrs.succeeded || 0) + ' / Fehlgeschlagen: ' + (attrs.failed || 0);
                if (attrs.failed > 0) {
                    message += '\n\nFehlgeschlagene Aufträge:\n';
                    (data.results || []).forEach(function (r) {
                        if (!r.attributes?.success) {
                            message += ' - Auftrag ' + r.attributes?.order_id + ': ' + (r.attributes?.error || 'Unbekannt') + '\n';
                        }
                    });
                }
                alert(message);

                // Close modal
                const modalInstance = bootstrap.Modal.getInstance(bulkCancelModal);
                if (modalInstance) modalInstance.hide();

                // Refresh page
                location.reload();
            })
            .catch(function (error) {
                alert('Fehler: ' + error.message);
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
                submitText.textContent = 'Stornieren';
            });
        });

        // Open modal when button clicked
        if (bulkDhlBtn && bulkModal) {
            bulkDhlBtn.addEventListener('click', function () {
                const checked = document.querySelectorAll('.order-checkbox:checked');
                const orderIds = Array.from(checked).map(function (cb) { return parseInt(cb.value, 10); });

                if (orderIds.length === 0) {
                    return;
                }

                // Set hidden values
                document.getElementById('selected-order-ids').value = JSON.stringify(orderIds);
                document.getElementById('selected-orders-count').textContent = orderIds.length;

                // Load DHL products
                fetchProducts();
            });
        }

        async function fetchProducts() {
            const productSelect = document.getElementById('product_id');
            if (!productSelect) return;

            productSelect.innerHTML = '<option value="">Laden…</option>';

            try {
                const response = await fetch('/api/admin/dhl/products', {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + getCsrfToken(),
                    }
                });

                if (!response.ok) throw new Error('Failed to load products');

                const data = await response.json();
                const products = data.data || [];

                productSelect.innerHTML = '<option value="">-- Bitte wählen --</option>';
                products.forEach(function (product) {
                    const attrs = product.attributes || {};
                    const option = document.createElement('option');
                    option.value = attrs.product_id || '';
                    option.textContent = attrs.name || attrs.product_id || '';
                    productSelect.appendChild(option);
                });
            } catch (error) {
                productSelect.innerHTML = '<option value="">Fehler beim Laden</option>';
            }
        }

        // Load services when product changes
        document.getElementById('product_id').addEventListener('change', function () {
            const productId = this.value;
            const container = document.getElementById('additional-services-container');

            if (!productId) {
                container.innerHTML = '<span class="text-muted">Produkt wählen, um Services zu laden …</span>';
                return;
            }

            container.innerHTML = '<span class="text-muted">Laden…</span>';

            fetch('/api/admin/dhl/services?product_id=' + encodeURIComponent(productId), {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + getCsrfToken(),
                }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                const services = data.data || [];
                container.innerHTML = '';

                if (services.length === 0) {
                    container.innerHTML = '<span class="text-muted">Keine Zusatzservices verfügbar</span>';
                    return;
                }

                services.forEach(function (service) {
                    const attrs = service.attributes || {};
                    const div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = '<input class="form-check-input" type="checkbox" name="additional_services[]" value="' + (attrs.service_code || '') + '" id="svc_' + (attrs.service_code || '') + '">' +
                        '<label class="form-check-label" for="svc_' + (attrs.service_code || '') + '">' + (attrs.name || attrs.service_code || '') + '</label>';
                    container.appendChild(div);
                });
            })
            .catch(function () {
                container.innerHTML = '<span class="text-danger">Fehler beim Laden der Services</span>';
            });
        });

        // Form submission
        document.getElementById('dhl-bulk-booking-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = document.getElementById('bulk-booking-spinner');
            const submitText = document.getElementById('bulk-booking-submit-text');

            // Collect additional services
            const services = [];
            form.querySelectorAll('input[name="additional_services[]"]:checked').forEach(function (cb) {
                services.push(cb.value);
            });
            document.getElementById('selected-services').value = JSON.stringify(services);

            // Disable button
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            submitText.textContent = 'Wird gebucht…';

            const orderIds = JSON.parse(document.getElementById('selected-order-ids').value || '[]');
            const productId = document.getElementById('product_id').value;
            const pickupDate = document.getElementById('pickup_date').value;

            fetch('/api/admin/dhl/bulk-book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + getCsrfToken(),
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    order_ids: orderIds,
                    product_id: productId,
                    additional_services: services,
                    pickup_date: pickupDate,
                })
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.errors && data.errors.length > 0) {
                    alert('Fehler: ' + (data.errors[0].detail || 'Unbekannt'));
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                    submitText.textContent = 'DHL buchen';
                    return;
                }

                const attrs = data.data?.attributes || {};
                if (attrs.queued) {
                    alert('Bulk-Buchung wurde in die Queue gelegt und wird im Hintergrund verarbeitet.');
                } else {
                    let message = 'Erfolgreich: ' + (attrs.succeeded || 0) + ' / Fehlgeschlagen: ' + (attrs.failed || 0);
                    if (attrs.failed > 0) {
                        message += '\n\nFehlgeschlagene Aufträge:\n';
                        (data.results || []).forEach(function (r) {
                            if (!r.attributes?.success) {
                                message += ' - Auftrag ' + r.attributes?.order_id + ': ' + (r.attributes?.error || 'Unbekannt') + '\n';
                            }
                        });
                    }
                    alert(message);
                }

                // Close modal
                const modalInstance = bootstrap.Modal.getInstance(bulkModal);
                if (modalInstance) modalInstance.hide();

                // Refresh page
                location.reload();
            })
            .catch(function (error) {
                alert('Fehler: ' + error.message);
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
                submitText.textContent = 'DHL buchen';
            });
        });

        function getCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        }
    });
    </script>
    @endsection
@endsection
