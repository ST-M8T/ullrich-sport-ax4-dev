@extends('layouts.admin', [
    'pageTitle' => 'Benutzer bearbeiten',
    'currentSection' => 'identity-users',
])

@section('content')
    <x-ui.page-header>
        <h1 class="mb-1">Benutzer bearbeiten</h1>
        <p class="text-muted mb-0">
            {{ $user->username() }}
            <span class="text-muted">(#{{ $user->id()->toInt() }})</span>
        </p>
        <x-slot:actions>
            <div class="d-flex gap-2">
                <a href="{{ route('identity-users.show', ['user' => $user->id()->toInt()]) }}" class="btn btn-secondary">
                    Zur Detailansicht
                </a>
                <a href="{{ route('identity-users') }}" class="btn btn-outline-secondary">
                    Zurück zur Übersicht
                </a>
            </div>
        </x-slot:actions>
    </x-ui.page-header>


    @if($isDisabled)
        <div class="alert alert-danger">
            Dieser Benutzer ist aktuell deaktiviert und kann sich nicht anmelden. Aktivieren Sie den Zugang hier oder über die Schnellaktion.
        </div>
    @endif

    @if($mustChange)
        <div class="alert alert-warning">
            Beim nächsten Login muss dieser Benutzer sein Passwort ändern. Entfernen Sie die Option unten, wenn das Passwort bereits bestätigt wurde.
        </div>
    @endif

    @if($disabledOld && !$isDisabled)
        <div class="alert alert-warning">
            Hinweis: Mit dem Speichern wird der Benutzer deaktiviert.
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Stammdaten</div>
        <div class="card-body">
            <x-forms.form method="PUT" action="{{ route('identity-users.update', ['user' => $user->id()->toInt()]) }}">
                <div class="row g-3">
                    @include('identity.users.partials.user-fields', [
                        'user' => $user,
                        'availableRoles' => $availableRoles ?? [],
                    ])
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <input type="hidden" name="must_change_password" value="0">
                        <x-forms.checkbox
                            name="must_change_password"
                            label="Passwortwechsel beim nächsten Login erzwingen"
                            :checked="$mustChangeOld"
                        />
                    </div>
                    <div class="col-md-6">
                        <input type="hidden" name="disabled" value="0">
                        <x-forms.checkbox
                            name="disabled"
                            label="Benutzerzugang deaktivieren"
                            :checked="$disabledOld"
                        />
                    </div>
                </div>

                <x-forms.form-actions
                    submit-label="Änderungen speichern"
                    cancel-url="{{ route('identity-users.show', ['user' => $user->id()->toInt()]) }}"
                />
            </x-forms.form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Schnellaktionen</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <h2 class="h6">Zugangsstatus</h2>
                    <p class="text-muted small mb-2">
                        Aktivieren oder deaktivieren Sie den Zugang ohne weitere Änderungen zu speichern.
                    </p>
                    <form method="post" action="{{ route('identity-users.update-status', ['user' => $user->id()->toInt()]) }}" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="hidden" name="disabled" value="{{ $user->disabled() ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $user->disabled() ? 'btn-success' : 'btn-outline-danger' }}">
                            {{ $user->disabled() ? 'Benutzer aktivieren' : 'Benutzer deaktivieren' }}
                        </button>
                        <span class="text-muted small">
                            Aktueller Status:
                            <strong>{{ $user->disabled() ? 'deaktiviert' : 'aktiv' }}</strong>
                        </span>
                    </form>
                </div>
                <div class="col-md-6">
                    <h2 class="h6">Passwort zurücksetzen</h2>
                    <p class="text-muted small mb-2">
                        Setzen Sie ein neues Passwort und entscheiden Sie, ob der Benutzer beim nächsten Login das Passwort ändern muss.
                    </p>
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
@endsection
