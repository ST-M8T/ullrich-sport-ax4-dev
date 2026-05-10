@extends('layouts.admin', [
    'pageTitle' => sprintf('Einstellung bearbeiten: %s', $setting->key()),
    'currentSection' => 'configuration-settings',
])

@section('content')
    <h1 class="mb-4">Einstellung bearbeiten</h1>

    @include('configuration.settings._form', [
        'action' => route('configuration-settings.update', ['settingKey' => $setting->key()]),
        'method' => 'PUT',
        'setting' => $setting,
        'valueTypes' => $valueTypes,
        'submitLabel' => 'Einstellung speichern',
    ])
@endsection
