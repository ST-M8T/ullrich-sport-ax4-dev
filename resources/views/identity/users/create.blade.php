@extends('layouts.admin', [
    'pageTitle' => 'Benutzer anlegen',
    'currentSection' => 'identity-users',
])

@section('content')
    <x-ui.page-header title="Neuen Benutzer anlegen" subtitle="Pflichtfelder sind mit *">
        <x-slot:actions>
            <a href="{{ route('identity-users') }}" class="btn btn-secondary">Zurück zur Übersicht</a>
        </x-slot:actions>
    </x-ui.page-header>

    @php
        $willBeDisabled = (string) old('disabled', '0') === '1';
        $willRequirePasswordChange = (string) old('must_change_password', '1') === '1';
    @endphp

    <div class="mb-4">
        <div class="alert alert-info mb-3">
            Standardmäßig müssen neue Benutzer ihr Passwort beim ersten Login ändern. Sie können die Option unten anpassen.
        </div>
        @if($willBeDisabled)
            <div class="alert alert-warning mb-0">
                Achtung: Der Benutzer wird deaktiviert erstellt und kann sich erst nach Aktivierung anmelden.
            </div>
        @endif
        @if(!$willRequirePasswordChange)
            <div class="alert alert-warning mt-3 mb-0">
                Der Passwortwechsel beim ersten Login ist deaktiviert. Stellen Sie sicher, dass das initiale Passwort sicher verteilt wird.
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">Benutzerdaten</div>
        <div class="card-body">
            <x-forms.form method="POST" action="{{ route('identity-users.store') }}">
                <div class="row g-3">
                    @include('identity.users.partials.user-fields', [
                        'user' => null,
                        'availableRoles' => $availableRoles ?? [],
                    ])

                    <div class="col-md-4">
                        <x-forms.input
                            name="password"
                            label="Initiales Passwort"
                            type="password"
                            min="8"
                            required
                        />
                        <small class="form-text text-muted">Mindestens 8 Zeichen, idealerweise mit Zahlen &amp; Sonderzeichen.</small>
                    </div>

                    <div class="col-md-4">
                        <x-forms.input
                            name="password_confirmation"
                            label="Passwort bestätigen"
                            type="password"
                            min="8"
                            required
                        />
                    </div>
                </div>

                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <input type="hidden" name="must_change_password" value="0">
                        <x-forms.checkbox
                            name="must_change_password"
                            label="Passwortwechsel beim ersten Login erzwingen"
                            :checked="(string) old('must_change_password', '1') === '1'"
                        />
                    </div>

                    <div class="col-md-6">
                        <input type="hidden" name="disabled" value="0">
                        <x-forms.checkbox
                            name="disabled"
                            label="Benutzer vorerst deaktivieren"
                            :checked="(string) old('disabled', '0') === '1'"
                        />
                    </div>
                </div>

                <x-forms.form-actions submit-label="Benutzer erstellen" cancel-url="{{ route('identity-users') }}" />
            </x-forms.form>
        </div>
    </div>
@endsection
