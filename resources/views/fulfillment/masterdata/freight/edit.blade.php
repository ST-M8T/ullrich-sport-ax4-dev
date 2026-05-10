@extends('layouts.admin', [
    'pageTitle' => 'Versandprofil bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Versandprofil bearbeiten</h1>
        <p class="text-muted mb-0">Passe das Label für Profil {{ $profile->shippingProfileId()->toInt() }} an.</p>
    </div>

    @include('fulfillment.masterdata.freight._form', [
        'profile' => $profile,
        'action' => route('fulfillment.masterdata.freight.update', $profile->shippingProfileId()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
