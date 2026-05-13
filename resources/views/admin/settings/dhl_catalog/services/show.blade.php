@extends('layouts.admin', [
    'pageTitle' => 'DHL Service',
    'currentSection' => 'admin-settings-dhl-catalog',
])

{{--
    Service-Detail (PROJ-6 / t16). Read-Only.

    §7  Presentation only.
    §51 <th scope>, Status nicht NUR per Farbe.
    §75 Wiederverwendete UI-Komponenten.
--}}

@php
    /** @var \App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlAdditionalService $service */
    /** @var array<string,mixed> $parameterSchema */

    $statusValue = $service->isDeprecated() ? 'deprecated' : 'active';

    // Flat-Rendering des JSON-Schemas (nur Top-Level Properties — der unterstuetzte
    // Subset deckt das gut ab, siehe JsonSchema VO).
    $required = $parameterSchema['required'] ?? [];
    $properties = $parameterSchema['properties'] ?? [];
@endphp

@section('content')
    <div class="admin-content">

        <x-ui.page-header :title="$service->code()" :subtitle="$service->name()">
            <x-slot:actions>
                <a href="{{ route('admin.settings.dhl.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left icon" aria-hidden="true"></i> Zurueck zur Uebersicht
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="card mb-4" aria-labelledby="dhl-service-meta-heading">
            <div class="card-body">
                <h2 id="dhl-service-meta-heading" class="visually-hidden">Stammdaten</h2>
                <dl class="row g-3 mb-0">
                    <div class="col-md-3">
                        <dt class="text-muted small">Code</dt>
                        <dd class="mb-0 fw-semibold">{{ $service->code() }}</dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Kategorie</dt>
                        <dd class="mb-0">{{ $service->category()->value }}</dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Status</dt>
                        <dd class="mb-0">
                            <x-dhl.catalog-status-badge :status="$statusValue" />
                        </dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Quelle</dt>
                        <dd class="mb-0">{{ $service->source()->value }}</dd>
                    </div>
                    <div class="col-12">
                        <dt class="text-muted small">Beschreibung</dt>
                        <dd class="mb-0">{{ $service->description() ?: '—' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- Parameter-Schema-Preview --}}
        <section class="card" aria-labelledby="dhl-service-schema-heading">
            <div class="card-body">
                <x-ui.section-header
                    title="Parameter-Schema"
                    description="Definiert, welche Parameter dieser Service beim Buchen erwartet."
                >
                    <x-slot:actions>
                        <span class="badge bg-light text-dark border">
                            type: {{ $parameterSchema['type'] ?? 'object' }}
                        </span>
                    </x-slot:actions>
                </x-ui.section-header>

                @if(empty($properties))
                    <x-ui.empty-state
                        title="Keine Parameter"
                        description="Dieser Service erwartet keine Eingabe-Parameter."
                    />
                @else
                    <x-ui.data-table dense striped>
                        <caption class="visually-hidden">Parameter-Schema fuer Service {{ $service->code() }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Feld</th>
                                <th scope="col">Typ</th>
                                <th scope="col">Required</th>
                                <th scope="col">Default</th>
                                <th scope="col">Constraint</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($properties as $name => $def)
                                @php
                                    $isRequired = in_array($name, (array) $required, true);
                                    $constraints = [];
                                    if (isset($def['enum']))    { $constraints[] = 'enum: ' . implode('|', (array) $def['enum']); }
                                    if (isset($def['minimum'])) { $constraints[] = 'min: '  . $def['minimum']; }
                                    if (isset($def['maximum'])) { $constraints[] = 'max: '  . $def['maximum']; }
                                    if (isset($def['format']))  { $constraints[] = 'format: '. $def['format']; }
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $name }}</td>
                                    <td><code class="small">{{ $def['type'] ?? '—' }}</code></td>
                                    <td>
                                        @if($isRequired)
                                            <span class="badge bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1">
                                                <i class="fa fa-asterisk icon" aria-hidden="true"></i> ja
                                            </span>
                                        @else
                                            <span class="text-muted small">nein</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(array_key_exists('default', $def))
                                            <code class="small">{{ json_encode($def['default'], JSON_UNESCAPED_UNICODE) }}</code>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($constraints !== [])
                                            <span class="small">{{ implode('; ', $constraints) }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>
                @endif
            </div>
        </section>
    </div>
@endsection
