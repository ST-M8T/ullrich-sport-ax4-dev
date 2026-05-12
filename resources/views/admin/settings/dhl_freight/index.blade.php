@extends('layouts.admin', [
    'pageTitle' => 'Versand: DHL Freight',
    'currentSection' => 'admin-settings-dhl-freight',
])

{{--
    Konsolidierte Settings-Seite "Versand → DHL Freight" (Task t11, UI Wave 6).
    Loest die drei verstreuten Alt-Seiten (configuration/settings tab "dhl",
    configuration/integrations dhl_freight, fulfillment/masterdata/freight) ab.

    Engineering-Handbuch:
    - §7  Presentation: Form sendet PUT auf Use Case, keine Fachlogik.
    - §51 Accessibility: <label for>, aria-required, aria-invalid, aria-describedby.
    - §53 Klare Zustaende: Initial-Setup-Hinweis, Test-Connection Loading/Erfolg/Fehler.
    - §56 Frontend-Security: Secrets nie an die View, nur "*_set"-Flag.
    - §75 DRY:    wiederverwendete Form-Components (x-forms.input, .checkbox, .form-actions).
                  Test-Connection JS lebt in einem zentralen Modul, nicht inline.
--}}

@php
    /** @var array<string,mixed> $configurationData */
    $isInitialSetup =
        ($configurationData['auth_client_id'] ?? '') === ''
        || ($configurationData['freight_api_key'] ?? '') === '';

    $secretPlaceholder = static fn (bool $isSet): string =>
        $isSet ? '•••• gesetzt (zum Aendern neuen Wert eingeben)' : 'nicht gesetzt';
@endphp

@section('content')
    <div class="admin-content">
        <x-ui.section-header
            title="DHL Freight Konfiguration"
            description="Zentrale Einstellungen fuer Authentifizierung, Freight-API, Tracking und Push-Webhook." />

        @if($isInitialSetup)
            <div class="alert alert-warning" role="status" data-testid="dhl-freight-initial-setup-hint">
                DHL Freight ist noch nicht vollstaendig konfiguriert.
                Bitte alle Pflichtfelder ausfuellen, damit Buchungen funktionieren.
            </div>
        @endif

        <x-forms.form
            :action="route('admin.settings.dhl-freight.update')"
            method="PUT">

            {{-- Section A — API-Authentifizierung (DHL OAuth) --}}
            <section class="col-12" aria-labelledby="dhl-freight-section-auth">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1" id="dhl-freight-section-auth">A. API-Authentifizierung (DHL OAuth)</h2>
                        <p class="text-muted small mb-3">
                            OAuth-Endpoint fuer DHL myAPI. Client-ID und Secret werden bei DHL beantragt.
                        </p>

                        <div class="row g-4">
                            <x-forms.input
                                name="auth_base_url"
                                label="Auth-Basis-URL"
                                type="url"
                                :value="$configurationData['auth_base_url']"
                                required
                                placeholder="https://api-eu.dhl.com/auth/v1" />

                            <x-forms.input
                                name="auth_client_id"
                                label="Auth Client-ID"
                                :value="$configurationData['auth_client_id']"
                                required />

                            <x-forms.input
                                name="auth_client_secret"
                                label="Auth Client-Secret"
                                type="password"
                                :placeholder="$secretPlaceholder($configurationData['auth_client_secret_set'])"
                                autocomplete="new-password">
                                <x-slot:help>
                                    Leer lassen, um den bestehenden Wert nicht zu aendern.
                                </x-slot:help>
                            </x-forms.input>

                            <div class="col-12">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-dhl-freight-test-connection
                                    data-url="{{ route('admin.settings.dhl-freight.test-connection') }}"
                                    aria-describedby="dhl-freight-test-connection-result">
                                    <i class="fa fa-plug icon" aria-hidden="true"></i>
                                    Verbindung testen
                                </button>
                                <span
                                    id="dhl-freight-test-connection-result"
                                    class="ms-2 align-middle"
                                    role="status"
                                    aria-live="polite"
                                    data-dhl-freight-test-connection-result></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section B — Freight-API --}}
            <section class="col-12" aria-labelledby="dhl-freight-section-freight">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1" id="dhl-freight-section-freight">B. Freight-API</h2>
                        <p class="text-muted small mb-3">
                            Endpoint und Credentials fuer DHL Freight Booking.
                        </p>

                        <div class="row g-4">
                            <x-forms.input
                                name="freight_base_url"
                                label="Freight-Basis-URL"
                                type="url"
                                :value="$configurationData['freight_base_url']"
                                required
                                placeholder="https://api-eu.dhl.com/freight/v1" />

                            <x-forms.input
                                name="freight_api_key"
                                label="Freight API-Key"
                                :value="$configurationData['freight_api_key']"
                                required />

                            <x-forms.input
                                name="freight_api_secret"
                                label="Freight API-Secret"
                                type="password"
                                :placeholder="$secretPlaceholder($configurationData['freight_api_secret_set'])"
                                autocomplete="new-password">
                                <x-slot:help>
                                    Leer lassen, um den bestehenden Wert nicht zu aendern.
                                </x-slot:help>
                            </x-forms.input>

                            <x-forms.input
                                name="timeout_seconds"
                                label="Timeout (Sekunden)"
                                type="number"
                                :value="$configurationData['timeout_seconds']"
                                required
                                min="1"
                                max="120"
                                step="1" />

                            <x-forms.checkbox
                                name="verify_ssl"
                                label="SSL-Zertifikat des DHL-Endpoints pruefen (empfohlen)"
                                :checked="(bool) $configurationData['verify_ssl']"
                                switch />
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section C — Standard-Konfiguration --}}
            <section class="col-12" aria-labelledby="dhl-freight-section-defaults">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1" id="dhl-freight-section-defaults">C. Standard-Konfiguration</h2>
                        <p class="text-muted small mb-3">
                            Fallback-Werte, wenn Freight-Profile keine eigenen Angaben enthalten.
                        </p>

                        <div class="row g-4">
                            <x-forms.input
                                name="default_account_number"
                                label="Standard-Account-Nummer"
                                :value="$configurationData['default_account_number']"
                                maxlength="15">
                                <x-slot:help>
                                    Wird verwendet, wenn ein Freight-Profile keine eigene Account-Nr. hat.
                                    Optional, sofern jedes Profile eine eigene Account-Nr. besitzt.
                                </x-slot:help>
                            </x-forms.input>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section D — Tracking --}}
            <section class="col-12" aria-labelledby="dhl-freight-section-tracking">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1" id="dhl-freight-section-tracking">D. Tracking</h2>
                        <p class="text-muted small mb-3">
                            Konfiguration fuer Statusabfragen via DHL Unified-Tracking-API.
                        </p>

                        <div class="row g-4">
                            <x-forms.input
                                name="tracking_api_key"
                                label="Tracking API-Key"
                                type="password"
                                :placeholder="$secretPlaceholder($configurationData['tracking_api_key_set'])"
                                autocomplete="new-password">
                                <x-slot:help>
                                    Leer lassen, um den bestehenden Wert nicht zu aendern.
                                </x-slot:help>
                            </x-forms.input>

                            <x-forms.input
                                name="tracking_default_service"
                                label="Default-Service"
                                :value="$configurationData['tracking_default_service']"
                                maxlength="50"
                                placeholder="z.B. freight" />

                            <x-forms.input
                                name="tracking_origin_country_code"
                                label="Origin-Country (ISO-2)"
                                :value="$configurationData['tracking_origin_country_code']"
                                maxlength="2"
                                placeholder="DE"
                                style="text-transform: uppercase" />

                            <x-forms.input
                                name="tracking_requester_country_code"
                                label="Requester-Country (ISO-2)"
                                :value="$configurationData['tracking_requester_country_code']"
                                maxlength="2"
                                placeholder="DE"
                                style="text-transform: uppercase" />
                        </div>
                    </div>
                </div>
            </section>

            {{-- Section E — Push (Webhook fuer Statusupdates) --}}
            <section class="col-12" aria-labelledby="dhl-freight-section-push">
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="h5 mb-1" id="dhl-freight-section-push">E. Push-Webhook</h2>
                        <p class="text-muted small mb-3">
                            Optionaler Endpoint fuer Push-Benachrichtigungen von DHL (Statusupdates).
                        </p>

                        <div class="row g-4">
                            <x-forms.input
                                name="push_base_url"
                                label="Push-Basis-URL"
                                type="url"
                                :value="$configurationData['push_base_url']"
                                placeholder="https://push.dhl.com/notifications/v1" />

                            <x-forms.input
                                name="push_api_key"
                                label="Push API-Key"
                                type="password"
                                :placeholder="$secretPlaceholder($configurationData['push_api_key_set'])"
                                autocomplete="new-password">
                                <x-slot:help>
                                    Leer lassen, um den bestehenden Wert nicht zu aendern.
                                </x-slot:help>
                            </x-forms.input>
                        </div>
                    </div>
                </div>
            </section>

            <x-slot:actions>
                <x-forms.form-actions
                    submitLabel="Einstellungen speichern"
                    :cancel-url="route('fulfillment-masterdata')"
                    cancel-label="Abbrechen" />
            </x-slot:actions>
        </x-forms.form>
    </div>
@endsection
