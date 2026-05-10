@extends('layouts.admin', [
    'pageTitle' => 'Anmeldung',
    'showSidebar' => false,
    'navigationItems' => [],
    'currentSection' => '',
])

@section('content')
    @php
        /** @var \Illuminate\Support\ViewErrorBag $errors */
        $loginErrors = $errors->login ?? null;
    @endphp

    <div class="full-height-center">
        <div class="card constrained-card border-0">
            <div class="card-body stack stack-sm p-4 p-lg-5">
                <div class="stack stack-xs text-center">
                    <div class="fs-4 fw-bold text-uppercase">AX4-Tool</div>
                    <p class="text-muted mb-0">Sendungsverwaltung für DHL-Freight</p>
                </div>

                @if($loginErrors?->any())
                    <div class="alert alert-error">
                        {{ $loginErrors->first() }}
                    </div>
                @endif

                <form method="post" action="{{ route('login.perform') }}" class="stack stack-sm">
                    @csrf

                    @php
                        $usernameError = $loginErrors?->first('username');
                        $passwordError = $loginErrors?->first('password');
                        $twoFactorError = $loginErrors?->first('two_factor_code');
                    @endphp

                    <div class="stack stack-xs">
                        <label class="form-label" for="username">Benutzername</label>
                        <input
                            id="username"
                            type="text"
                            name="username"
                            class="form-control {{ $usernameError ? 'is-invalid' : '' }}"
                            value="{{ old('username') }}"
                            required
                            autofocus
                            autocomplete="username"
                        >
                        @if($usernameError)
                            <div class="invalid-feedback">{{ $usernameError }}</div>
                        @endif
                    </div>

                    <div class="stack stack-xs">
                        <label class="form-label" for="password">Passwort</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="form-control {{ $passwordError ? 'is-invalid' : '' }}"
                            required
                            autocomplete="current-password"
                        >
                        @if($passwordError)
                            <div class="invalid-feedback">{{ $passwordError }}</div>
                        @endif
                    </div>

                    <div class="stack stack-xs">
                        <label class="form-label" for="two_factor_code">
                            Zwei-Faktor-Code <span class="text-muted">(falls erforderlich)</span>
                        </label>
                        <input
                            id="two_factor_code"
                            type="text"
                            name="two_factor_code"
                            class="form-control {{ $twoFactorError ? 'is-invalid' : '' }}"
                            value="{{ old('two_factor_code') }}"
                            autocomplete="one-time-code"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="123456"
                        >
                        @if($twoFactorError)
                            <div class="invalid-feedback">{{ $twoFactorError }}</div>
                        @endif
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <div class="form-check">
                            <input
                                id="remember"
                                type="checkbox"
                                name="remember"
                                value="1"
                                class="form-check-input"
                                {{ old('remember') ? 'checked' : '' }}
                            >
                            <label for="remember" class="form-check-label">Angemeldet bleiben</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Anmelden</button>
                </form>
            </div>
        </div>
    </div>
@endsection
