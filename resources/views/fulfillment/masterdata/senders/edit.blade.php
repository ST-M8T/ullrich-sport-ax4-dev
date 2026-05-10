@extends('layouts.admin', [
    'pageTitle' => 'Senderprofil bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
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
