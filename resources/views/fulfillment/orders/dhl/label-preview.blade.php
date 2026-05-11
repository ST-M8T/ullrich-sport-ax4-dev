@extends('layouts.admin', [
    'pageTitle' => 'DHL-Label Vorschau',
    'currentSection' => 'fulfillment-orders',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Aufträge', 'url' => route('fulfillment-orders')],
        ['label' => '#' . $order->id()->toInt(), 'url' => route('fulfillment-orders.show', $order->id()->toInt())],
        ['label' => 'DHL-Label'],
    ],
])

@section('content')
    <div class="label-preview-page">
        <div class="label-preview-header mb-4">
            <h1 class="mb-0">DHL-Label Vorschau</h1>
            <p class="text-muted mb-0">Auftrag #{{ $order->externalOrderId() }}</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <x-ui.info-card title="DHL-Label">
                    <div class="label-image-container text-center p-4 bg-light rounded">
                        @if(!empty($labelData['label_pdf_base64']))
                            <img
                                src="data:image/png;base64,{{ $labelData['label_pdf_base64'] }}"
                                alt="DHL Label"
                                class="img-fluid label-image"
                                style="max-height: 400px;"
                            >
                        @elseif(!empty($labelData['label_url']))
                            <img
                                src="{{ $labelData['label_url'] }}"
                                alt="DHL Label"
                                class="img-fluid label-image"
                                style="max-height: 400px;"
                            >
                        @else
                            <div class="alert alert-warning mb-0">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                Kein Label vorhanden.
                            </div>
                        @endif
                    </div>
                </x-ui.info-card>
            </div>

            <div class="col-lg-4">
                <x-ui.info-card title="Sendungsdaten">
                    <x-ui.definition-list
                        :items="[
                            ['label' => 'DHL-Shipment-ID', 'value' => $order->dhlShipmentId() ?? '—'],
                            ['label' => 'Produkt-ID', 'value' => $labelData['product_id'] ?? '—'],
                            ['label' => 'Abholreferenz', 'value' => $labelData['pickup_reference'] ?? '—'],
                            ['label' => 'Erstellt am', 'value' => $labelData['generated_at'] ?? '—'],
                        ]"
                    />

                    <hr>

                    <h3 class="h6 mb-3">Trackingnummern</h3>
                    @if(empty($labelData['tracking_numbers']))
                        <p class="text-muted mb-0">Keine vorhanden.</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach($labelData['tracking_numbers'] as $trackingNumber)
                                <li class="badge bg-light text-dark py-2 px-3 mb-1 d-block">{{ $trackingNumber }}</li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.info-card>

                <div class="d-flex flex-column gap-2 mt-4">
                    <a href="{{ $downloadUrl }}" class="btn btn-primary">
                        <i class="fa fa-download me-2"></i>Als PDF herunterladen
                    </a>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.close()">
                        <i class="fa fa-times me-2"></i>Fenster schließen
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection
