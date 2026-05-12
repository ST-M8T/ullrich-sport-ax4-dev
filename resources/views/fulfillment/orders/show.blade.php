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
    @php
        $contactValue = new \Illuminate\Support\HtmlString(
            e($order->contactEmail() ?? '—') . '<br><small class="text-muted">' . e($order->contactPhone() ?? '—') . '</small>'
        );
        $senderValue = new \Illuminate\Support\HtmlString(
            e($order->senderCode() ?? '—') . '<br><small class="text-muted">' . e($order->destinationCountry() ?? '??') . '</small>'
        );
        $senderProfiles = collect($senderProfiles ?? []);
        $currentSenderProfile = $order->senderProfileId() !== null
            ? $senderProfiles->first(fn ($profile) => $profile->id()->toInt() === $order->senderProfileId()->toInt())
            : null;
        $hasBookableSenderProfile = $currentSenderProfile !== null;
        $senderProfileOptions = $senderProfiles
            ->mapWithKeys(fn ($profile) => [
                $profile->id()->toInt() => $profile->displayName() . ' (' . $profile->senderCode() . ')',
            ])
            ->all();
        $totalValue = new \Illuminate\Support\HtmlString(
            e($order->totalAmount() !== null ? number_format($order->totalAmount(), 2, ',', '.') : '—')
            . ' <small class="text-muted">' . e($order->currency()) . '</small>'
        );

        $orderSummaryLeftItems = [
            ['label' => 'Interne ID', 'value' => $order->id()->toInt()],
            ['label' => 'Kunde', 'value' => $order->customerNumber() ?? '—'],
            ['label' => 'Kontakt', 'value' => $contactValue],
            ['label' => 'Sender', 'value' => $senderValue],
        ];

        $orderSummaryRightItems = [
            ['label' => 'Summe', 'value' => $totalValue],
            ['label' => 'Status', 'value' => view('components.order-status', ['order' => $order])],
            ['label' => 'Verarbeitet', 'value' => $order->processedAt()?->format('d.m.Y H:i') ?? '—'],
            ['label' => 'Aktualisiert', 'value' => $order->updatedAt()->format('d.m.Y H:i')],
        ];
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h1 class="mb-0">Auftrag #{{ $order->externalOrderId() }}</h1>
        <a href="{{ route('fulfillment-orders') }}" class="btn btn-outline-secondary">Zurück zur Übersicht</a>
    </div>

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
                    <x-ui.definition-list :items="$orderSummaryLeftItems" />
                    <x-ui.definition-list :items="$orderSummaryRightItems" />
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
                @if(!$order->dhlShipmentId())
                    <h3 class="h6 mb-3">Senderprofil</h3>
                    @if($currentSenderProfile)
                        <div class="alert alert-success mb-3">
                            <strong>{{ $currentSenderProfile->displayName() }}</strong><br>
                            <small>{{ $currentSenderProfile->senderCode() }}</small>
                        </div>
                    @else
                        <div class="alert alert-warning mb-3">
                            Vor der DHL-Buchung muss ein Senderprofil zugeordnet werden.
                        </div>
                    @endif

                    @if($senderProfiles->isEmpty())
                        <a href="{{ route('fulfillment.masterdata.senders.create') }}" class="btn btn-outline-primary w-100 mb-3">
                            Senderprofil anlegen
                        </a>
                    @else
                        <div class="mb-3">
                            <x-forms.form method="POST" action="{{ route('fulfillment-orders.sender-profile', $order->id()->toInt()) }}">
                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                <x-forms.select
                                    name="sender_profile_id"
                                    label="Senderprofil"
                                    :options="$senderProfileOptions"
                                    :value="$order->senderProfileId()?->toInt()"
                                    :required="true"
                                    col-class="col-12"
                                />
                                <x-slot:actions>
                                    <button type="submit" class="btn btn-outline-secondary w-100 mt-3">Senderprofil zuordnen</button>
                                </x-slot:actions>
                            </x-forms.form>
                        </div>
                    @endif

                    <hr>
                @endif

                @if(!$order->isBooked())
                    <div class="mb-3">
                        <x-forms.form method="POST" action="{{ route('fulfillment-orders.book', $order->id()->toInt()) }}">
                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                            <x-slot:actions>
                                <button type="submit" class="btn btn-primary w-100">Auftrag buchen</button>
                            </x-slot:actions>
                        </x-forms.form>
                    </div>
                    <p class="text-muted small mb-3">
                        Der Auftrag wird als gebucht markiert und mit aktuellem Zeitstempel versehen.
                    </p>
                    <hr>
                    <h3 class="h6 mb-3">DHL-Buchung</h3>
                    @if($hasBookableSenderProfile)
                        @php
                            $dhlProductsUrl = url('/api/admin/dhl/products');
                            $dhlProductDefault = old('product_code', $dhlProductIdDefault ?? '');
                        @endphp
                        <x-forms.form method="POST" action="{{ route('fulfillment-orders.dhl.book', $order->id()->toInt()) }}">
                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                            <div class="col-12 mb-3"
                                 data-dhl-product-selector
                                 data-products-url="{{ $dhlProductsUrl }}"
                                 data-default-product-code="{{ $dhlProductDefault }}">
                                <label for="dhl-product-select" class="form-label">
                                    DHL-Produkt <span class="text-danger">*</span>
                                </label>
                                <select
                                    id="dhl-product-select"
                                    name="product_code"
                                    class="form-select form-select-sm"
                                    required
                                    aria-busy="true"
                                    data-dhl-product-select
                                    disabled
                                >
                                    <option value="">Lade Produkte …</option>
                                </select>
                                <div class="form-text small text-muted"
                                     role="status"
                                     aria-live="polite"
                                     data-dhl-product-status>
                                    Produkte werden geladen …
                                </div>
                            </div>
                            <x-slot:actions>
                                <button type="submit" class="btn btn-outline-primary w-100 mt-3">Bei DHL buchen</button>
                            </x-slot:actions>
                        </x-forms.form>
                        <p class="text-muted small mt-2 mb-0">
                            Bucht den Auftrag direkt bei DHL und erstellt eine Sendung.
                        </p>
                    @else
                        <button type="button" class="btn btn-outline-primary w-100" disabled>Bei DHL buchen</button>
                        <p class="text-muted small mt-2 mb-0">
                            Ordne zuerst ein Senderprofil zu.
                        </p>
                    @endif
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
                        <x-slot:actions>
                            <button type="submit" class="btn btn-outline-secondary w-100 mt-3">Tracking-Transfer starten</button>
                        </x-slot:actions>
                    </x-forms.form>
                    <p class="text-muted small mt-2 mb-3">
                        Erstellt einen Tracking-Event für die gewählten Sendungen. Bei Bedarf kann eine einzelne Nummer übertragen werden.
                    </p>
                    @if($order->dhlShipmentId())
                        <hr>
                        <h3 class="h6 mb-3">DHL-Aktionen</h3>
                        @if($order->dhlCancelledAt())
                            <div class="alert alert-danger mb-3">
                                <strong>STORNIERT</strong>
                                <br><small>
                                    am {{ \Carbon\Carbon::parse($order->dhlCancelledAt())->format('d.m.Y H:i') }}
                                    von {{ $order->dhlCancelledBy() }}
                                </small>
                                @if($order->dhlCancellationReason())
                                    <br><small>Grund: {{ $order->dhlCancellationReason() }}</small>
                                @endif
                            </div>
                            <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                Stornierung vorhanden
                            </button>
                        @else
                            @if($order->dhlLabelUrl() || $order->dhlLabelPdfBase64())
                                <a href="{{ route('fulfillment-orders.dhl.label', $order->id()->toInt()) }}" class="btn btn-outline-success w-100 mb-2">
                                    Label Vorschau
                                </a>
                            @else
                                <a href="{{ route('fulfillment-orders.dhl.label', $order->id()->toInt()) }}" class="btn btn-outline-success w-100 mb-2">
                                    Label generieren
                                </a>
                            @endif
                            <button type="button" class="btn btn-outline-info w-100 mb-2" onclick="loadPriceQuote({{ $order->id()->toInt() }})">
                                Preisabfrage
                            </button>
                            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelDhlModal">
                                DHL-Sendung stornieren
                            </button>
                            <div id="price-quote-result" class="mt-2 hidden"></div>
                        @endif
                    @elseif($hasBookableSenderProfile)
                        <hr>
                        <h3 class="h6 mb-3">DHL-Buchung</h3>
                        <x-dhl.product-catalog-modal
                            :order-id="$order->id()->toInt()"
                        />
                    @elseif(!$order->dhlShipmentId())
                        <hr>
                        <h3 class="h6 mb-3">DHL-Buchung</h3>
                        <button type="button" class="btn btn-outline-primary w-100" disabled>Bei DHL buchen</button>
                        <p class="text-muted small mt-2 mb-0">
                            Ordne zuerst ein Senderprofil zu.
                        </p>
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

    @if(!$order->isBooked() && $hasBookableSenderProfile)
        @include('fulfillment.orders._dhl-package-editor', [
            'order' => $order,
            'packages' => $packages,
            'defaultPackageType' => 'PAL',
            'bookingActionUrl' => route('fulfillment-orders.dhl.book', $order->id()->toInt()),
            'productCode' => old('product_code', ''),
            'payerCode' => old('payer_code', ''),
        ])
    @endif

    <x-ui.info-card title="Artikel ({{ $totalItemCount }})">
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
    </x-ui.info-card>

    <x-ui.info-card>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Pakete ({{ $totalPackageCount }})</h2>
            <span class="text-muted small">Gesamtgewicht: {{ number_format($totalPackageWeight, 2, ',', '.') }} kg</span>
        </div>
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
    </x-ui.info-card>

    <x-ui.info-card title="Sendungsverfolgung ({{ count($shipments) }})">
            @php
                // Index shipmentsWithLabels by tracking number for easy lookup
                $shipmentsByTracking = [];
                foreach ($shipmentsWithLabels as $shipmentData) {
                    $shipmentsByTracking[$shipmentData['tracking_number']] = $shipmentData;
                }
            @endphp
            @forelse($shipments as $shipment)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div>
                            <strong class="fs-6">{{ $shipment->trackingNumber() }}</strong>
                            <br>
                            <span class="text-muted small">{{ $shipment->carrierCode() }}</span>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('dhl.tracking.events', ['trackingNumber' => $shipment->trackingNumber()]) }}"
                               class="btn btn-outline-secondary btn-sm"
                               target="_blank"
                               title="API JSON anzeigen"
                            >
                                <span class="me-1">&#128259;</span> API
                            </a>
                        </div>
                    </div>

                    {{-- Timeline Component --}}
                    @if(isset($shipmentsByTracking[$shipment->trackingNumber()]))
                        @php
                            $shipmentData = $shipmentsByTracking[$shipment->trackingNumber()];
                        @endphp
                        <x-dhl.tracking-timeline
                            :events="$shipmentData['events']"
                            :current-status="$shipmentData['current_status']"
                            :is-delivered="$shipmentData['is_delivered']"
                            :tracking-number="$shipment->trackingNumber()"
                            :show-refresh-button="true"
                        />
                    @else
                        <div class="text-muted text-center py-3">
                            <small>Tracking-Daten werden geladen...</small>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">Es sind keine Sendungen mit diesem Auftrag verknüpft.</p>
            @endforelse
    </x-ui.info-card>

    {{-- Cancellation Modal --}}
    @if($order->dhlShipmentId() && !$order->dhlCancelledAt())
        <x-ui.modal title="DHL-Sendung stornieren" id="cancelDhlModal" size="md">
            <form method="POST" action="{{ route('fulfillment-orders.dhl.cancel', $order->id()->toInt()) }}">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                <div class="mb-3">
                    <p class="text-muted">
                        Möchten Sie die DHL-Sendung <strong>{{ $order->dhlShipmentId() }}</strong> wirklich stornieren?
                    </p>
                    <label for="cancel_reason" class="form-label">Stornierungsgrund (optional)</label>
                    <textarea class="form-control" id="cancel_reason" name="reason" rows="3" maxlength="500" placeholder="z.B. Kunde hat storniert, doppelte Buchung, etc."></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Stornieren</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
@endsection
