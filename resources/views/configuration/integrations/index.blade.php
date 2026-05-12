@extends('layouts.admin', [
    'pageTitle' => 'Integrationen',
    'currentSection' => 'configuration-settings',
])


@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="mb-0">Integrationen</h1>
            <p class="text-muted mb-0">Externe Systeme und Dienste konfigurieren und verwalten.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    <div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
        <div>
            <strong>Hinweis:</strong>
            DHL Freight-Konfiguration (Auth, Freight, Defaults, Tracking, Push) wurde zentralisiert.
        </div>
        <a href="{{ route('admin.settings.dhl-freight.index') }}" class="btn btn-sm btn-primary">
            Zu Versand → DHL Freight
        </a>
    </div>

    @php
        // dhl_freight wurde nach admin.settings.dhl-freight.index ausgelagert (siehe Banner oben).
        // Provider bleibt im Registry registriert, damit interne Validierung erhalten bleibt
        // (Engineering-Handbuch §75: keine doppelten UI-Strukturen).
        $integrationsByType = collect($integrationsByType)
            ->map(fn ($providers) => collect($providers)
                ->reject(fn ($provider) => $provider->key() === 'dhl_freight')
                ->values()
                ->all())
            ->reject(fn ($providers) => empty($providers))
            ->all();
    @endphp

    @foreach($integrationsByType as $typeKey => $providers)
        @php
            $type = $integrationTypeEnum::from($typeKey);
            $typeLabel = $typeLabels[$typeKey] ?? $typeKey;
        @endphp

        <section class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">{{ $typeLabel }}</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($providers as $provider)
                        @php
                            $integrationService = app(\App\Application\Integrations\IntegrationSettingsService::class);
                            $config = $integrationService->getConfiguration($provider->key());
                            $isConfigured = !empty(array_filter($config, fn($v) => $v !== null && $v !== ''));
                        @endphp
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 {{ $isConfigured ? 'border-success' : '' }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h3 class="h6 mb-0">{{ $provider->name() }}</h3>
                                        @if($isConfigured)
                                            <span class="badge" data-tone="success">Konfiguriert</span>
                                        @else
                                            <span class="badge" data-tone="warning">Nicht konfiguriert</span>
                                        @endif
                                    </div>
                                    <p class="text-muted small mb-3">{{ $provider->description() }}</p>
                                    <a 
                                        href="{{ route('configuration-integrations.show', ['integrationKey' => $provider->key()]) }}" 
                                        class="btn btn-sm {{ $isConfigured ? 'btn-outline-primary' : 'btn-primary' }}"
                                    >
                                        {{ $isConfigured ? 'Bearbeiten' : 'Konfigurieren' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach
@endsection

