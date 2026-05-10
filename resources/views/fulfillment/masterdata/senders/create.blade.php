@extends('layouts.admin', [
    'pageTitle' => 'Senderprofil anlegen',
    'currentSection' => 'fulfillment-masterdata',
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Neues Senderprofil</h1>
        <p class="text-muted mb-0">Hinterlege Absenderdaten für Neutral-Versand oder interne Logistik.</p>
    </div>

    @include('fulfillment.masterdata.senders._form', [
        'action' => route('fulfillment.masterdata.senders.store'),
        'submitLabel' => 'Senderprofil speichern',
    ])
@endsection
