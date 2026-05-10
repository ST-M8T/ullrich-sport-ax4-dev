@extends('layouts.admin', [
    'pageTitle' => 'Neue Mail-Vorlage',
    'currentSection' => 'configuration-mail-templates',
])

@section('content')
    <h1 class="mb-4">Neue Mail-Vorlage</h1>

    @include('configuration.mail-templates._form', [
        'action' => route('configuration-mail-templates.store'),
        'method' => 'POST',
        'submitLabel' => 'Vorlage speichern',
    ])
@endsection
