@extends('layouts.admin', [
    'pageTitle' => 'Fulfillment Order Detail',
    'currentSection' => 'fulfillment-orders',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Aufträge', 'url' => route('fulfillment-orders')],
        ['label' => '#' . $order->id()->toInt()],
    ],
])


@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="mb-0">Auftrag #{{ $order->externalOrderId() }}</h1>
        <a href="{{ route('fulfillment-orders') }}" class="btn btn-outline-secondary">Zurück zur Übersicht</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <x-ui.info-card title="Auftragsdaten">
                <div class="row g-3">
                    <x-ui.definition-list
                        :items="[
                            ['label' => 'Interne ID', 'value' => $order->id()->toInt()],
                            ['label' => 'Kunde', 'value' => $order->customerNumber() ?? '—'],
                            ['label' => 'Kontakt', 'value' => ($order->contactEmail() ?? '—') . '<br><small class=\"text-muted\">' . ($order->contactPhone() ?? '—') . '</small>'],
                            ['label' => 'Sender', 'value' => ($order->senderCode() ?? '—') . '<br><small class=\"text-muted\">' . ($order->destinationCountry() ?? '??') . '</small>'],
                        ]"
                    />
                    <x-ui.definition-list
                        :items="[
                            ['label' => 'Summe', 'value' => ($order->totalAmount() !== null ? number_format($order->totalAmount(), 2, ',', '.') : '—') . ' <small class=\"text-muted\">' . $order->currency() . '</small>'],
                            ['label' => 'Status', 'value' => view('components.order-status', ['order' => $order])],
                            ['label' => 'Verarbeitet', 'value' => $order->processedAt()?->format('d.m.Y H:i') ?? '—'],
                            ['label' => 'Aktualisiert', 'value' => $order->updatedAt()->format('d.m.Y H:i')],
                        ]"
                    />
                </div>
                <hr>
                <p class="mb-0">
                    <strong>Trackingnummern:</strong>
                    @if($trackingNumbers === [])
                        <span class="text-muted">Keine vorhanden.</span>
                    @else
                        @foreach($trackingNumbers as $trackingNumber)
                            <span class="badge bg-light text-dark me-1">{{ $trackingNumber }}</span>
                        @endforeach
                    @endif
                </p>
            </x-ui.info-card>
        </div>
        <div class="col-lg-4">
            <x-ui.action-card>
                    @if(!$order->isBooked())
                        <x-forms.form method="POST" action="{{ route('fulfillment-orders.book', $order->id()->toInt()) }}" class="mb-3">
                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                            <button type="submit" class="btn btn-primary w-100">Auftrag buchen</button>
                        </x-forms.form>
                        <p class="text-muted small mb-3">
                            Der Auftrag wird als gebucht markiert und mit aktuellem Zeitstempel versehen.
                        </p>
                        <hr>
                        <h3 class="h6 mb-3">DHL-Buchung</h3>
                        <x-forms.form method="POST" action="{{ route('fulfillment-orders.dhl.book', $order->id()->toInt()) }}">
                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                            <x-forms.input
                                name="product_id"
                                label="Produkt-ID (optional)"
                                type="text"
                                :value="old('product_id')"
                                placeholder="Standard-Produkt"
                                class="form-control-sm"
                                col-class="col-12"
                            />
                            <button type="submit" class="btn btn-outline-primary w-100">Bei DHL buchen</button>
                        </x-forms.form>
                        <p class="text-muted small mt-2 mb-0">
                            Bucht den Auftrag direkt bei DHL und erstellt eine Sendung.
                        </p>
                    @else
                        <x-forms.form method="POST" action="{{ route('fulfillment-orders.transfer', $order->id()->toInt()) }}">
                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                            <x-forms.input
                                name="tracking_number"
                                label="Trackingnummer"
                                type="text"
                                :value="old('tracking_number')"
                                placeholder="leer = alle vorhandenen"
                                col-class="col-12"
                            >
                                <x-slot:help>
                                    Leer lassen, um alle hinterlegten Nummern zu übertragen.
                                </x-slot:help>
                            </x-forms.input>
                            <x-forms.checkbox
                                name="sync_immediately"
                                label="Sofortige Synchronisierung anstoßen"
                                :checked="old('sync_immediately')"
                                col-class="col-12"
                            />
                            <button type="submit" class="btn btn-outline-secondary w-100">Tracking-Transfer starten</button>
                        </x-forms.form>
                        <p class="text-muted small mt-2 mb-3">
                            Erstellt einen Tracking-Event für die gewählten Sendungen. Bei Bedarf kann eine einzelne Nummer übertragen werden.
                        </p>
                        @if($order->dhlShipmentId())
                            <hr>
                            <h3 class="h6 mb-3">DHL-Aktionen</h3>
                            @if($order->dhlLabelUrl() || $order->dhlLabelPdfBase64())
                                <a href="{{ route('fulfillment-orders.dhl.label', $order->id()->toInt()) }}" class="btn btn-outline-success w-100 mb-2" target="_blank">
                                    Label herunterladen
                                </a>
                            @else
                                <a href="{{ route('fulfillment-orders.dhl.label', $order->id()->toInt()) }}" class="btn btn-outline-success w-100 mb-2">
                                    Label generieren
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-info w-100" onclick="loadPriceQuote({{ $order->id()->toInt() }})">
                                Preisabfrage
                            </button>
                            <div id="price-quote-result" class="mt-2 hidden"></div>
                        @elseif($order->senderProfileId())
                            <hr>
                            <h3 class="h6 mb-3">DHL-Buchung</h3>
                            <x-forms.form method="POST" action="{{ route('fulfillment-orders.dhl.book', $order->id()->toInt()) }}">
                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                <x-forms.input
                                    name="product_id"
                                    label="Produkt-ID (optional)"
                                    type="text"
                                    :value="old('product_id')"
                                    placeholder="Standard-Produkt"
                                    class="form-control-sm"
                                    col-class="col-12"
                                />
                                <button type="submit" class="btn btn-outline-primary w-100">Bei DHL buchen</button>
                            </x-forms.form>
                        @endif
                    @endif
            </x-ui.action-card>
        </div>
    </div>

    @if($order->dhlShipmentId())
        <script>
            function loadPriceQuote(orderId) {
                const resultDiv = document.getElementById('price-quote-result');
                resultDiv.style.display = 'block';
                resultDiv.innerHTML = '<div class="text-muted">Lade Preisabfrage...</div>';

                fetch('{{ route('fulfillment-orders.dhl.price-quote', $order->id()->toInt()) }}?product_id=' + (document.getElementById('dhl_product_id_booked')?.value || ''))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resultDiv.innerHTML = `
                                <div class="alert alert-success">
                                    <strong>Preis:</strong> ${data.price.toFixed(2)} ${data.currency || 'EUR'}<br>
                                    ${data.breakdown && Object.keys(data.breakdown).length > 0 ? '<small>Details verfügbar</small>' : ''}
                                </div>
                            `;
                        } else {
                            resultDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Fehler bei Preisabfrage'}</div>`;
                        }
                    })
                    .catch(error => {
                        resultDiv.innerHTML = `<div class="alert alert-danger">Fehler: ${error.message}</div>`;
                    });
            }
        </script>
    @endif

    <x-ui.info-card title="Artikel ({{ $totalItemCount }})">
            <div class="table-responsive">
                <x-ui.data-table dense striped>
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Beschreibung</th>
                        <th scope="col">Menge</th>
                        <th scope="col">Gewicht (kg)</th>
                        <th scope="col">Assembly</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item->id()->toInt() }}</td>
                            <td>{{ $item->sku() ?? '—' }}</td>
                            <td>{{ $item->description() ?? '—' }}</td>
                            <td>{{ $item->quantity() }}</td>
                            <td>{{ $item->weightKg() !== null ? number_format($item->weightKg(), 2, ',', '.') : '—' }}</td>
                            <td>
                                @if($item->isAssembly())
                                    <span class="badge bg-info text-dark">Ja</span>
                                @else
                                    <span class="badge bg-light text-dark">Nein</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center">Keine Artikel hinterlegt.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
    </x-ui.info-card>

    <x-ui.info-card>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Pakete ({{ $totalPackageCount }})</h2>
            <span class="text-muted small">Gesamtgewicht: {{ number_format($totalPackageWeight, 2, ',', '.') }} kg</span>
        </div>
            <div class="table-responsive">
                <x-ui.data-table dense striped>
                    <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Referenz</th>
                        <th scope="col">Profil</th>
                        <th scope="col">Menge</th>
                        <th scope="col">Gewicht (kg)</th>
                        <th scope="col">Abmessungen (mm)</th>
                        <th scope="col">Truck Slots</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>{{ $package->id()->toInt() }}</td>
                            <td>{{ $package->packageReference() ?? '—' }}</td>
                            <td>{{ $package->packagingProfileId()?->toInt() ?? '—' }}</td>
                            <td>{{ $package->quantity() }}</td>
                            <td>{{ $package->weightKg() !== null ? number_format($package->weightKg(), 2, ',', '.') : '—' }}</td>
                            <td>
                                @php
                                    $dimensions = array_filter([
                                        $package->lengthMillimetres(),
                                        $package->widthMillimetres(),
                                        $package->heightMillimetres(),
                                    ]);
                                @endphp
                                {{ $dimensions !== [] ? implode(' × ', $dimensions) : '—' }}
                            </td>
                            <td>{{ $package->truckSlotUnits() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-muted text-center">Keine Pakete hinterlegt.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.data-table>
            </div>
    </x-ui.info-card>

    <x-ui.info-card title="Sendungen ({{ count($shipments) }})">
            @forelse($shipments as $shipment)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <strong>{{ $shipment->trackingNumber() }}</strong><br>
                            <span class="text-muted">{{ $shipment->carrierCode() }}</span>
                        </div>
                        <div class="text-end">
                            <div>Status: {{ $shipment->statusDescription() ?? $shipment->statusCode() ?? '—' }}</div>
                            <small class="text-muted">
                                Letztes Event: {{ $shipment->lastEventAt()?->format('d.m.Y H:i') ?? '—' }}<br>
                                Geliefert: {{ $shipment->deliveredAt()?->format('d.m.Y H:i') ?? '—' }}
                            </small>
                        </div>
                    </div>
                    <div class="table-responsive mt-3">
                        <x-ui.data-table dense striped>
                            <thead>
                            <tr>
                                <th scope="col">Event-Code</th>
                                <th scope="col">Status</th>
                                <th scope="col">Beschreibung</th>
                                <th scope="col">Ort</th>
                                <th scope="col">Datum</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($shipment->events() as $event)
                                <tr>
                                    <td>{{ $event->eventCode() ?? '—' }}</td>
                                    <td>{{ $event->status() ?? '—' }}</td>
                                    <td>{{ $event->description() ?? '—' }}</td>
                                    <td>
                                        {{ $event->facility() ?? '—' }}
                                        @if($event->city())
                                            <br><small class="text-muted">{{ $event->city() }} {{ $event->country() ?? '' }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $event->occurredAt()->format('d.m.Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-muted text-center">Keine Ereignisse vorhanden.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </x-ui.data-table>
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Es sind keine Sendungen mit diesem Auftrag verknüpft.</p>
            @endforelse
    </x-ui.info-card>
@endsection
