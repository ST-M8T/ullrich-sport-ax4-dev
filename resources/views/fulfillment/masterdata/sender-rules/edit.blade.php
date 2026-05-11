@extends('layouts.admin', [
    'pageTitle' => 'Sender-Regel bearbeiten',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten', 'url' => route('fulfillment-masterdata')],
        ['label' => 'Regeln', 'url' => route('fulfillment.masterdata.sender-rules.index')],
        ['label' => '#' . $rule->id()->toInt()],
    ],
])

@section('content')
    <div class="mb-4">
        <h1 class="h3 mb-1">Sender-Regel bearbeiten</h1>
        <p class="text-muted mb-0">Passe Priorität oder Ziel-Sender der Regel #{{ $rule->id()->toInt() }} an.</p>
    </div>

    @include('fulfillment.masterdata.sender-rules._form', [
        'rule' => $rule,
        'senderProfiles' => $senderProfiles,
        'ruleTypes' => $ruleTypes,
        'action' => route('fulfillment.masterdata.sender-rules.update', $rule->id()->toInt()),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
@endsection
