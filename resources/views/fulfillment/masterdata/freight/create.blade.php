@extends('layouts.admin', [
    'pageTitle' => 'Versandprofil anlegen',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Versand', 'url' => route('fulfillment.masterdata.freight.index')],
        ['label' => 'Neu'],
    ],
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Neues Versandprofil</h1>
        <p class="text-muted mb-0">Hinterlege eine Plenty-Versandprofil-ID und optionales Label.</p>
    </div>

    @include('fulfillment.masterdata.freight._form', [
        'action' => route('fulfillment.masterdata.freight.store'),
        'submitLabel' => 'Versandprofil speichern',
    ])
@endsection
