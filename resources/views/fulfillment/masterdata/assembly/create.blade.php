@extends('layouts.admin', [
    'pageTitle' => 'Vormontage anlegen',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Neue Vormontage-Option</h1>
        <p class="text-muted mb-0">Definiere das Verpackungsprofil und optionale Metadaten für einen vormontierten Artikel.</p>
    </div>

    @include('fulfillment.masterdata.assembly._form', [
        'packagingProfiles' => $packagingProfiles,
        'action' => route('fulfillment.masterdata.assembly.store'),
        'submitLabel' => 'Vormontage speichern',
    ])
@endsection
