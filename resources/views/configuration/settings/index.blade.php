@extends('layouts.admin', [
    'pageTitle' => 'Systemeinstellungen',
    'currentSection' => 'configuration-settings',
])

@php
    /** @var \Illuminate\Support\Collection $settings */
    $groupSlugs = array_keys($groups);
@endphp

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="mb-0">Systemeinstellungen</h1>
            <p class="text-muted mb-0">Setup, Stammdaten und Konfigurationsgruppen an einem Ort.</p>
        </div>
        <a href="{{ route('configuration-settings.create') }}" class="btn btn-outline-secondary">
            Spezial-Setting hinzufügen
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Fehler:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sidebar-tabs-layout">
        <aside class="sidebar-tabs-layout__sidebar">
            <x-sidebar-tabs
                :tabs="$primaryTabs"
                :active-tab="$activeTab"
                :base-url="route('configuration-settings')"
                tab-param="tab"
                title="Systemeinstellungen"
                description="Setup, Stammdaten und Konfigurationsgruppen an einem Ort."
            />
        </aside>

        <main class="sidebar-tabs-layout__content">
            @switch($activeTab)
                @case('settings')
                    @include('configuration.settings.partials.settings', [
                        'groups' => $groups,
                        'settings' => $settings,
                        'groupTabParam' => $groupTabParam,
                        'activeGroup' => $activeGroup,
                    ])
                    @break

                @case('masterdata')
                    @include('configuration.settings.partials.masterdata', ['catalog' => $catalog ?? null])
                    @break

                @case('monitoring')
                    @include('configuration.settings.partials.monitoring', ['availableMonitoring' => $availableMonitoring ?? []])
                    @break

                @case('logs')
                    @include('configuration.settings.partials.logs', ['availableTools' => $availableLogTools ?? []])
                    @break

                @case('verwaltung')
                    @include('configuration.settings.partials.verwaltung', ['availableVerwaltung' => $availableVerwaltung ?? []])
                    @break
            @endswitch
        </main>
    </div>

    <div class="app-modal" data-settings-modal aria-hidden="true">
        <div class="app-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="settings-modal-title">
            <div class="app-modal__header">
                <div>
                    <h3 class="app-modal__title h4" id="settings-modal-title" data-settings-modal-title>Details</h3>
                    <p class="app-modal__subtitle text-muted small" data-settings-modal-subtitle></p>
                </div>
                <button type="button" class="btn-close app-modal__close" aria-label="Schließen" data-settings-modal-close></button>
            </div>
            <div class="app-modal__body" data-settings-modal-body></div>
        </div>
        <div class="app-modal__backdrop" data-settings-modal-close></div>
    </div>
@endsection
