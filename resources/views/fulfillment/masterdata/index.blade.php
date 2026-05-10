@extends('layouts.admin', [
    'pageTitle' => 'Fulfillment-Stammdaten',
    'currentSection' => 'fulfillment-masterdata',
    'breadcrumbs' => [
        ['label' => 'Fulfillment', 'url' => route('fulfillment-orders')],
        ['label' => 'Stammdaten'],
    ],
])

@section('content')
    @include('fulfillment.masterdata.partials.catalog', [
        'catalog' => $catalog,
        'masterdataTabParam' => 'tab',
    ])
@endsection
