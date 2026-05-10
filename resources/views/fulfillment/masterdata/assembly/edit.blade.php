@extends('layouts.admin', [
    'pageTitle' => 'Vormontage bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Vormontage bearbeiten</h1>
        <p class="text-muted mb-0">
            Artikel #{{ $option->assemblyItemId() }} - Verpackung neu zuordnen oder beschreiben.
        </p>
    </div>

    @include('fulfillment.masterdata.assembly._form', [
        'option' => $option,
        'packagingProfiles' => $packagingProfiles,
        'action' => route('fulfillment.masterdata.assembly.update', $option->id()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
