@extends('layouts.admin', [
    'pageTitle' => sprintf('Mail-Vorlage bearbeiten: %s', $template->templateKey()),
    'currentSection' => 'configuration-mail-templates',
])

@section('content')
    <h1 class="mb-4">Mail-Vorlage bearbeiten</h1>

    @include('configuration.mail-templates._form', [
        'action' => route('configuration-mail-templates.update', ['templateKey' => $template->templateKey()]),
        'method' => 'PUT',
        'template' => $template,
        'submitLabel' => 'Vorlage aktualisieren',
    ])
@endsection
