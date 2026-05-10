@extends('layouts.admin', [
    'pageTitle' => 'Benutzerprofil',
    'currentSection' => 'identity-users',
])

@section('content')
    <x-ui.page-header>
        <h1 class="mb-1">Benutzer: {{ $user->username() }}</h1>
        <p class="text-muted mb-0">ID #{{ $user->id()->toInt() }}</p>
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('identity-users.edit', ['user' => $user->id()->toInt()]) }}" class="btn btn-primary">
                    Bearbeiten
                </a>
                <a href="{{ route('identity-users') }}" class="btn btn-secondary">Zurück zur Übersicht</a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $isDisabled = $user->disabled();
        $mustChangePassword = $user->requiresPasswordChange();
    @endphp

    @if($isDisabled)
        <div class="alert alert-danger">
            Dieser Benutzer ist deaktiviert und kann sich derzeit nicht anmelden. Nutzen Sie die Aktionen unten, um den Zugang zu aktivieren.
        </div>
    @endif

    @if($mustChangePassword)
        <div class="alert alert-warning">
            Beim nächsten Login muss dieser Benutzer sein Passwort ändern. Sie können diese Vorgabe in den Stammdaten anpassen.
        </div>
    @endif

    @if(!$user->email())
        <div class="alert alert-info">
            Für diesen Benutzer ist keine E-Mail-Adresse hinterlegt. Passwort-Rücksetzungen per Mail sind dadurch nicht möglich.
        </div>
    @endif

    <div class="row g-4 mb-4">
        <x-ui.info-card header="Allgemeine Informationen">
            <x-ui.definition-list
                :items="[
                    ['label' => 'Anzeigename', 'value' => $user->displayName() ?? '—'],
                    ['label' => 'E-Mail', 'value' => $user->email() ?? '—'],
                    ['label' => 'Rolle', 'value' => '<span class=\"badge bg-secondary text-uppercase\">' . $user->role() . '</span><span class=\"d-block small text-muted\">' . ($roleMetadata['label'] ?? '—') . '</span>'],
                    ['label' => 'Status', 'value' => $user->disabled() ? '<span class=\"badge bg-danger\">Deaktiviert</span>' : '<span class=\"badge bg-success\">Aktiv</span>'],
                    ['label' => 'Passwortwechsel', 'value' => $user->requiresPasswordChange() ? '<span class=\"badge bg-warning text-dark\">Erforderlich</span>' : '<span class=\"badge bg-success\">Nicht erforderlich</span>'],
                ]"
            />
        </x-ui.info-card>
        <x-ui.info-card header="Zeiten">
            <x-ui.definition-list
                :items="[
                    ['label' => 'Erstellt', 'value' => $user->createdAt()->format('d.m.Y H:i')],
                    ['label' => 'Aktualisiert', 'value' => $user->updatedAt()->format('d.m.Y H:i')],
                    ['label' => 'Letzter Login', 'value' => $user->lastLoginAt()?->format('d.m.Y H:i') ?? '—'],
                ]"
            />
        </x-ui.info-card>
    </div>

    <div class="card mb-4">
        <div class="card-header">Schnellaktionen</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h2 class="h6">Zugangsstatus</h2>
                    <p class="text-muted small mb-2">Aktueller Status: <strong>{{ $user->disabled() ? 'deaktiviert' : 'aktiv' }}</strong></p>
                    <form method="post" action="{{ route('identity-users.update-status', ['user' => $user->id()->toInt()]) }}" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="hidden" name="disabled" value="{{ $user->disabled() ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $user->disabled() ? 'btn-success' : 'btn-outline-danger' }}">
                            {{ $user->disabled() ? 'Benutzer aktivieren' : 'Benutzer deaktivieren' }}
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h2 class="h6">Passwort zurücksetzen</h2>
                    <p class="text-muted small mb-2">Setzen Sie ein neues Passwort und entscheiden Sie, ob beim nächsten Login ein Wechsel erforderlich ist.</p>
                    <x-forms.form method="POST" action="{{ route('identity-users.reset-password', ['user' => $user->id()->toInt()]) }}">
                        <x-forms.input
                            name="new_password"
                            label="Neues Passwort"
                            type="password"
                            min="8"
                            required
                            col-class="col-12"
                        />
                        <x-forms.input
                            name="new_password_confirmation"
                            label="Passwort bestätigen"
                            type="password"
                            min="8"
                            required
                            col-class="col-12"
                        />
                        <input type="hidden" name="require_password_change" value="0">
                        <x-forms.checkbox
                            name="require_password_change"
                            label="Passwortwechsel beim nächsten Login erzwingen"
                            :checked="(string) old('require_password_change', '1') === '1'"
                            col-class="col-12"
                        />
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-primary">Passwort setzen</button>
                        </div>
                    </x-forms.form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Rolle & Berechtigungen</div>
        <div class="card-body">
            <div class="mb-3">
                <h2 class="h6 mb-1">Aktive Rolle</h2>
                <p class="mb-0">
                    <span class="badge bg-secondary text-uppercase">{{ $user->role() }}</span>
                    <span class="ms-2">{{ $roleMetadata['label'] ?? 'Unbekannt' }}</span>
                </p>
                @if(!empty($roleMetadata['description']))
                    <p class="text-muted small mb-0">{{ $roleMetadata['description'] }}</p>
                @endif
            </div>
            <div>
                <h3 class="h6">Berechtigungen</h3>
                @if(empty($permissionDetails))
                    <p class="text-muted small">Es sind keine Berechtigungen hinterlegt.</p>
                @else
                    <ul class="list-unstyled mb-0">
                        @foreach($permissionDetails as $permission)
                            <li class="mb-2">
                                <span class="badge bg-light text-dark border">{{ $permission['permission'] }}</span>
                                <span class="ms-2">{{ $permission['label'] }}</span>
                                @if(!empty($permission['description']))
                                    <span class="d-block text-muted small ms-4">{{ $permission['description'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <section>
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h2 class="h4 mb-0">Login-Versuche</h2>
                <small class="text-muted">Letzte {{ count($loginAttempts) }} Einträge</small>
            </div>
            <form method="get" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0" for="attempts">Anzahl</label>
                <input type="number" name="attempts" id="attempts" value="{{ request('attempts', 10) }}" min="1" max="50" class="form-control form-control-sm field-sm">
                <button type="submit" class="btn btn-sm btn-primary">Aktualisieren</button>
            </form>
        </div>

        <div class="table-responsive">
            <x-ui.data-table dense striped>
                <thead>
                <tr>
                    <th scope="col">Zeitpunkt</th>
                    <th scope="col">Erfolg</th>
                    <th scope="col">IP</th>
                    <th scope="col">User-Agent</th>
                    <th scope="col">Grund</th>
                </tr>
                </thead>
                <tbody>
                @forelse($loginAttempts as $attempt)
                    <tr>
                        <td>{{ $attempt->createdAt()->format('d.m.Y H:i:s') }}</td>
                        <td>
                            @if($attempt->success())
                                <span class="badge bg-success">Erfolgreich</span>
                            @else
                                <span class="badge bg-danger">Fehlgeschlagen</span>
                            @endif
                        </td>
                        <td>{{ $attempt->ipAddress() ?? '—' }}</td>
                        <td class="small">{{ $attempt->userAgent() ?? '—' }}</td>
                        <td>{{ $attempt->failureReason() ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">Keine Login-Versuche vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </x-ui.data-table>
        </div>
    </section>
@endsection
