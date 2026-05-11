@extends('layouts.admin', [
    'pageTitle' => 'Verpackungsprofil erstellen',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Verpackung', 'url' => route('fulfillment.masterdata.packaging.index')],
        ['label' => 'Neu'],
    ],
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Verpackungsprofil erstellen</h1>
        <p class="text-muted mb-0">Lege einen neuen Paletten- oder Verpackungstyp mit Kapazitäten und Stapelregeln an.</p>
    </div>

    @include('fulfillment.masterdata.packaging._form', [
        'action' => route('fulfillment.masterdata.packaging.store'),
        'submitLabel' => 'Profil anlegen',
    ])
@endsection
