@extends('layouts.admin', [
    'pageTitle' => 'Neue Systemeinstellung',
    'currentSection' => 'configuration-settings',
])

@section('content')
    <h1 class="mb-4">Neue Systemeinstellung</h1>

    @include('configuration.settings._form', [
        'action' => route('configuration-settings.store'),
        'method' => 'POST',
        'valueTypes' => $valueTypes,
        'submitLabel' => 'Einstellung anlegen',
    ])
@endsection
