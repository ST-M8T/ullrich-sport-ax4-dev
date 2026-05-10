@extends('layouts.admin', [
    'pageTitle' => 'Verpackungsprofil bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Verpackungsprofil bearbeiten</h1>
        <p class="text-muted mb-0">
            Passe Maße, Kapazitäten und Stapelregeln für <strong>{{ $profile->packageName() }}</strong> an.
        </p>
    </div>

    @include('fulfillment.masterdata.packaging._form', [
        'profile' => $profile,
        'action' => route('fulfillment.masterdata.packaging.update', $profile->id()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
