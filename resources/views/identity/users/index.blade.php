@extends('layouts.admin', [
    'pageTitle' => 'Benutzerverwaltung',
    'currentSection' => 'identity-users',
    'breadcrumbs' => [
        ['label' => 'Identity'],
        ['label' => 'Benutzerverwaltung'],
    ],
])

@section('content')
    <x-ui.page-header title="Benutzerverwaltung">
        <x-slot:actions>
            <a href="{{ route('identity-users.create') }}" class="btn btn-primary">Neuen Benutzer anlegen</a>
        </x-slot:actions>
    </x-ui.page-header>


    <div class="card mb-4">
        <div class="card-body">
            <x-filters.filter-form :action="route('identity-users')" :filters="$filters ?? []">
                <x-forms.input
                    name="username"
                    label="Username"
                    type="text"
                    :value="$filters['username'] ?? ''"
                    placeholder="user..."
                    col-class="col-md-3"
                />
                <x-forms.select
                    name="role"
                    label="Rolle"
                    :options="collect($roleChoices)->mapWithKeys(fn($meta, $key) => [$key => $meta['label']])->prepend('Alle', '')->all()"
                    :value="$selectedRoleFilter"
                    col-class="col-md-3"
                />
                <x-forms.select
                    name="disabled"
                    label="Status"
                    :options="['' => 'Alle', '0' => 'Aktiv', '1' => 'Deaktiviert']"
                    :value="(string)($filters['disabled'] ?? '')"
                    col-class="col-md-3"
                />
                <x-forms.select
                    name="must_change_password"
                    label="Passwortwechsel nötig"
                    :options="['' => 'Alle', '1' => 'Ja', '0' => 'Nein']"
                    :value="(string)($filters['must_change_password'] ?? '')"
                    col-class="col-md-3"
                />
            </x-filters.filter-form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Benutzerliste</h2>
        </div>
        <div class="table-responsive">
            <x-ui.data-table striped hover>
            <thead>
                <tr>
                <th scope="col">Username</th>
                <th scope="col">Name</th>
                <th scope="col">E-Mail</th>
                <th scope="col">Rolle</th>
                <th scope="col">Status</th>
                <th scope="col">Letzter Login</th>
                <th scope="col">Erstellt</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                @php
                    $statusBadge = $user->disabled() ? 'bg-danger' : 'bg-success';
                    $roleSlug = $user->role();
                    $roleLabel = $roleChoices[$roleSlug]['label'] ?? $roleSlug;
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('identity-users.show', ['user' => $user->id()->toInt()]) }}" class="text-decoration-none">
                            <strong>{{ $user->username() }}</strong>
                        </a>
                        @if($user->requiresPasswordChange())
                            <span class="badge bg-warning text-dark ms-1">PW erforderlich</span>
                        @endif
                    </td>
                    <td>{{ $user->displayName() ?? '—' }}</td>
                    <td>{{ $user->email() ?? '—' }}</td>
                    <td>
                        <span class="badge bg-secondary text-uppercase">{{ $roleSlug }}</span>
                        <span class="d-block small text-muted">{{ $roleLabel }}</span>
                    </td>
                    <td>
                        <span class="badge {{ $statusBadge }}">{{ $user->disabled() ? 'Deaktiviert' : 'Aktiv' }}</span>
                    </td>
                    <td>{{ $user->lastLoginAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $user->createdAt()->format('d.m.Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-ui.empty-state
                            title="Keine Benutzer gefunden"
                            description="Es wurden keine Benutzer gefunden. Passen Sie die Filter an oder legen Sie neue Benutzer an."
                            :actions="[['label' => 'Neuen Benutzer anlegen', 'style' => 'primary', 'url' => route('identity-users.create')]]"
                        />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
        </div>
    </div>
@endsection
