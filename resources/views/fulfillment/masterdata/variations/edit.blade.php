@extends('layouts.admin', [
    'pageTitle' => 'Variantenprofil bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Variantenprofil bearbeiten</h1>
        <p class="text-muted mb-0">
            Item {{ $profile->itemId() }} – Variation {{ $profile->variationId() ?? '—' }} anpassen.
        </p>
    </div>

    @include('fulfillment.masterdata.variations._form', [
        'profile' => $profile,
        'packagingProfiles' => $packagingProfiles,
        'assemblyOptions' => $assemblyOptions,
        'action' => route('fulfillment.masterdata.variations.update', $profile->id()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
