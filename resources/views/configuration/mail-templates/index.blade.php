@extends('layouts.admin', [
    'pageTitle' => 'Mail-Vorlagen',
    'currentSection' => 'configuration-mail-templates',
])

@section('content')
    <h1 class="mb-4">Mail-Vorlagen</h1>

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('configuration-mail-templates.create') }}" class="btn btn-primary">
            Neue Vorlage anlegen
        </a>
    </div>

    <div class="table-responsive">
        <x-ui.data-table dense striped>
            <thead>
            <tr>
                <th scope="col">Key</th>
                <th scope="col">Beschreibung</th>
                <th scope="col">Betreff</th>
                <th scope="col">Status</th>
                <th scope="col">Aktualisiert von</th>
                <th scope="col">Aktualisiert am</th>
                <th scope="col" class="text-end">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            @forelse($templates as $template)
                <tr>
                    <td><code>{{ $template->templateKey() }}</code></td>
                    <td>{{ $template->description() ?? '—' }}</td>
                    <td>{{ $template->subject() }}</td>
                    <td>
                        @if($template->isActive())
                            <span class="badge bg-success">Aktiv</span>
                        @else
                            <span class="badge bg-secondary">Inaktiv</span>
                        @endif
                    </td>
                    <td>{{ $template->updatedByUserId() ?? '—' }}</td>
                    <td>{{ $template->updatedAt()->format('d.m.Y H:i') }}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <a href="{{ route('configuration-mail-templates.preview', ['templateKey' => $template->templateKey()]) }}" class="btn btn-outline-info">
                                Preview
                            </a>
                            <a href="{{ route('configuration-mail-templates.edit', ['templateKey' => $template->templateKey()]) }}" class="btn btn-outline-secondary">
                                Bearbeiten
                            </a>
                            <form method="post" action="{{ route('configuration-mail-templates.destroy', ['templateKey' => $template->templateKey()]) }}" class="d-inline" onsubmit="return confirm('Vorlage wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">Löschen</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">Keine Mail-Vorlagen vorhanden.</td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>
@endsection
