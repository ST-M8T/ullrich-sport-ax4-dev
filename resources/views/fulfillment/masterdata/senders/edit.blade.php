@extends('layouts.admin', [
    'pageTitle' => 'Senderprofil bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Absender', 'url' => route('fulfillment.masterdata.senders.index')],
        ['label' => $profile->displayName()],
    ],
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Senderprofil bearbeiten</h1>
        <p class="text-muted mb-0">Aktualisiere Kontakt- und Adressdaten für <strong>{{ $profile->displayName() }}</strong>.</p>
    </div>

    @include('fulfillment.masterdata.senders._form', [
        'profile' => $profile,
        'action' => route('fulfillment.masterdata.senders.update', $profile->id()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
