@extends('layouts.admin', [
    'pageTitle' => sprintf('Preview: %s', $template->templateKey()),
    'currentSection' => 'configuration-mail-templates',
])

@section('content')
    <x-ui.page-header title="Mail-Vorlagen-Vorschau">
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('configuration-mail-templates.edit', ['templateKey' => $template->templateKey()]) }}" class="btn btn-secondary">Bearbeiten</a>
                <a href="{{ route('configuration-mail-templates') }}" class="btn btn-outline-secondary">Zur Übersicht</a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <dl class="row mb-4">
        <dt class="col-sm-3">Key</dt>
        <dd class="col-sm-9"><code>{{ $template->templateKey() }}</code></dd>

        <dt class="col-sm-3">Beschreibung</dt>
        <dd class="col-sm-9">{{ $template->description() ?? '—' }}</dd>

        <dt class="col-sm-3">Betreff</dt>
        <dd class="col-sm-9">{{ $template->subject() }}</dd>

        <dt class="col-sm-3">Status</dt>
        <dd class="col-sm-9">
            @if($template->isActive())
                <span class="badge bg-success">Aktiv</span>
            @else
                <span class="badge bg-secondary">Inaktiv</span>
            @endif
        </dd>
    </dl>

    @if($template->bodyHtml())
        <h2 class="h4">HTML Vorschau</h2>
        <div class="border rounded p-3 mb-4 bg-white">
            {!! $template->bodyHtml() !!}
        </div>
    @endif

    @if($template->bodyText())
        <h2 class="h4">Text Variante</h2>
        <pre class="border rounded p-3 bg-light">{{ $template->bodyText() }}</pre>
    @endif

    @if(!$template->bodyHtml() && !$template->bodyText())
        <div class="alert alert-warning">Keine Inhalte hinterlegt.</div>
    @endif
@endsection
