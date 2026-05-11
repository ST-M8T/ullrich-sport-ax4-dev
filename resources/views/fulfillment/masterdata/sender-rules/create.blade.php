@extends('layouts.admin', [
    'pageTitle' => 'Sender-Regel anlegen',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Regeln', 'url' => route('fulfillment.masterdata.sender-rules.index')],
        ['label' => 'Neu'],
    ],
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Neue Sender-Regel</h1>
        <p class="text-muted mb-0">Definiere Priorität, Vergleichswert und Ziel-Sender.</p>
    </div>

    @include('fulfillment.masterdata.sender-rules._form', [
        'senderProfiles' => $senderProfiles,
        'ruleTypes' => $ruleTypes,
        'action' => route('fulfillment.masterdata.sender-rules.store'),
        'submitLabel' => 'Regel speichern',
    ])
@endsection
