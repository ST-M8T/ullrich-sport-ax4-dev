@extends('layouts.admin', [
    'pageTitle' => 'Variantenprofil anlegen',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Neues Variantenprofil</h1>
        <p class="text-muted mb-0">Erstelle ein Fulfillment-Profil für Item und Variation inklusive Standard-Verpackung.</p>
    </div>

    @include('fulfillment.masterdata.variations._form', [
        'packagingProfiles' => $packagingProfiles,
        'assemblyOptions' => $assemblyOptions,
        'action' => route('fulfillment.masterdata.variations.store'),
        'submitLabel' => 'Profil speichern',
    ])
@endsection
