@extends('layouts.admin', [
    'pageTitle' => 'DHL Produkt',
    'currentSection' => 'admin-settings-dhl-catalog',
])

{{--
    Produkt-Detail (PROJ-6 / t16). Read-Only.

    §7  Presentation only.
    §51 Accessibility: Tabs mit aria-selected/aria-controls/role, <th scope>.
    §75 DRY: x-dhl.catalog-status-badge, x-ui.* Komponenten wiederverwendet.
--}}

@php
    /** @var \App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlProduct $product */
    /** @var list<array<string,mixed>> $assignments */
    /** @var list<array<string,mixed>> $auditEntries */
    /** @var bool $canViewAudit */

    $code = $product->code()->value;
    $statusValue = $product->isDeprecated() ? 'deprecated' : 'active';

    $fromCountries = array_map(static fn ($c) => $c->value, $product->fromCountries());
    $toCountries   = array_map(static fn ($c) => $c->value, $product->toCountries());

    // Tabs via Query-Param `tab`. Default = routings.
    $activeTab = request()->query('tab', 'routings');
    if (! in_array($activeTab, ['routings', 'services', 'audit'], true)) {
        $activeTab = 'routings';
    }

    // Services nach Kategorie gruppieren (Engineering-Handbuch §39 — UI gruppiert,
    // Domain-Daten bleiben unveraendert).
    $byCategory = [];
    foreach ($assignments as $a) {
        $byCategory[$a['service_category']][] = $a;
    }
    ksort($byCategory);

    $categoryLabels = [
        'pickup'          => 'Pickup',
        'delivery'        => 'Delivery',
        'notification'    => 'Notification',
        'dangerous_goods' => 'Dangerous Goods',
        'special'         => 'Special',
        'unknown'         => 'Unbekannt',
    ];

    $requirementVariant = [
        'allowed'  => ['class' => 'bg-secondary-subtle text-secondary-emphasis', 'icon' => 'fa-check', 'label' => 'erlaubt'],
        'required' => ['class' => 'bg-primary-subtle text-primary-emphasis',     'icon' => 'fa-asterisk', 'label' => 'pflicht'],
        'forbidden'=> ['class' => 'bg-danger-subtle text-danger-emphasis',       'icon' => 'fa-times',    'label' => 'verboten'],
    ];

    $wl = $product->weightLimits();
    $dl = $product->dimensionLimits();

    $tabUrl = static fn (string $tab): string => route(
        'admin.settings.dhl.catalog.product.show',
        ['code' => $code, 'tab' => $tab],
    );
@endphp

@section('content')
    <div class="admin-content">

        <x-ui.page-header :title="$code" :subtitle="$product->name()">
            <x-slot:actions>
                <a href="{{ route('admin.settings.dhl.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left icon" aria-hidden="true"></i> Zurueck zur Uebersicht
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Header-Card mit Stammdaten --}}
        <section class="card mb-4" aria-labelledby="dhl-product-meta-heading">
            <div class="card-body">
                <h2 id="dhl-product-meta-heading" class="visually-hidden">Stammdaten</h2>

                <dl class="row g-3 mb-0">
                    <div class="col-md-3">
                        <dt class="text-muted small">Code</dt>
                        <dd class="mb-0 fw-semibold">{{ $code }}</dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Status</dt>
                        <dd class="mb-0">
                            <x-dhl.catalog-status-badge :status="$statusValue" />
                        </dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Quelle</dt>
                        <dd class="mb-0">{{ $product->source()->value }}</dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Markt</dt>
                        <dd class="mb-0">{{ $product->marketAvailability()->value }}</dd>
                    </div>

                    <div class="col-md-6">
                        <dt class="text-muted small">Beschreibung</dt>
                        <dd class="mb-0">{{ $product->description() ?: '—' }}</dd>
                    </div>

                    <div class="col-md-3">
                        <dt class="text-muted small">Gueltig von</dt>
                        <dd class="mb-0">
                            <time datetime="{{ $product->validFrom()->format(DATE_ATOM) }}">
                                {{ $product->validFrom()->format('d.m.Y') }}
                            </time>
                        </dd>
                    </div>
                    <div class="col-md-3">
                        <dt class="text-muted small">Gueltig bis</dt>
                        <dd class="mb-0">
                            @if($product->validUntil() !== null)
                                <time datetime="{{ $product->validUntil()->format(DATE_ATOM) }}">
                                    {{ $product->validUntil()->format('d.m.Y') }}
                                </time>
                            @else
                                <span class="text-muted">unbegrenzt</span>
                            @endif
                        </dd>
                    </div>

                    @if($product->isDeprecated())
                        <div class="col-md-3">
                            <dt class="text-muted small">Deprecated seit</dt>
                            <dd class="mb-0">
                                <time datetime="{{ $product->deprecatedAt()->format(DATE_ATOM) }}">
                                    {{ $product->deprecatedAt()->format('d.m.Y') }}
                                </time>
                            </dd>
                        </div>
                        <div class="col-md-3">
                            <dt class="text-muted small">Nachfolger</dt>
                            <dd class="mb-0">
                                @if($product->replacedByCode() !== null)
                                    <a href="{{ route('admin.settings.dhl.catalog.product.show', ['code' => $product->replacedByCode()->value]) }}">
                                        {{ $product->replacedByCode()->value }}
                                    </a>
                                @else
                                    <span class="text-muted">— nicht gesetzt</span>
                                @endif
                            </dd>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <dt class="text-muted small">Gewicht (kg)</dt>
                        <dd class="mb-0">{{ $wl->minKg }} – {{ $wl->maxKg }} kg</dd>
                    </div>
                    <div class="col-md-6">
                        <dt class="text-muted small">Maximale Masse (LxBxH, cm)</dt>
                        <dd class="mb-0">{{ $dl->maxLengthCm }} × {{ $dl->maxWidthCm }} × {{ $dl->maxHeightCm }} cm</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- Tabs (Anchor-basiert, kein JS-State noetig). --}}
        <ul class="nav nav-tabs mb-3" role="tablist" aria-label="Detail-Tabs">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'routings' ? 'active' : '' }}"
                   href="{{ $tabUrl('routings') }}"
                   role="tab"
                   aria-selected="{{ $activeTab === 'routings' ? 'true' : 'false' }}"
                   aria-controls="dhl-product-tab-routings">
                    Routings
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'services' ? 'active' : '' }}"
                   href="{{ $tabUrl('services') }}"
                   role="tab"
                   aria-selected="{{ $activeTab === 'services' ? 'true' : 'false' }}"
                   aria-controls="dhl-product-tab-services">
                    Services
                    <span class="badge bg-light text-dark ms-1">{{ count($assignments) }}</span>
                </a>
            </li>
            @if($canViewAudit)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab === 'audit' ? 'active' : '' }}"
                       href="{{ $tabUrl('audit') }}"
                       role="tab"
                       aria-selected="{{ $activeTab === 'audit' ? 'true' : 'false' }}"
                       aria-controls="dhl-product-tab-audit">
                        Audit
                        <span class="badge bg-light text-dark ms-1">{{ count($auditEntries) }}</span>
                    </a>
                </li>
            @endif
        </ul>

        @if($activeTab === 'routings')
            <section id="dhl-product-tab-routings" role="tabpanel" aria-labelledby="tab-routings" class="card">
                <div class="card-body">
                    <x-ui.section-header
                        title="Routings"
                        description="Unterstuetzte Versand-Kombinationen aus Von- und Nach-Laendern."
                    />
                    @if($fromCountries === [] || $toCountries === [])
                        <x-ui.empty-state title="Keine Routings hinterlegt" />
                    @else
                        <x-ui.data-table dense>
                            <caption class="visually-hidden">Routing-Matrix</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Von / Nach</th>
                                    @foreach($toCountries as $to)
                                        <th scope="col" class="text-center">{{ $to }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fromCountries as $from)
                                    <tr>
                                        <th scope="row">{{ $from }}</th>
                                        @foreach($toCountries as $to)
                                            <td class="text-center">
                                                <i class="fa fa-check text-success" aria-label="Unterstuetzt"></i>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.data-table>
                    @endif
                </div>
            </section>
        @elseif($activeTab === 'services')
            <section id="dhl-product-tab-services" role="tabpanel" aria-labelledby="tab-services" class="card">
                <div class="card-body">
                    <x-ui.section-header
                        title="Additional Services"
                        :count="count($assignments)"
                        description="Gruppiert nach Service-Kategorie."
                    />

                    @if(count($assignments) === 0)
                        <x-ui.empty-state title="Keine Services zugeordnet" />
                    @else
                        @foreach($byCategory as $category => $rows)
                            <h3 class="h6 mt-4 mb-2">{{ $categoryLabels[$category] ?? $category }}</h3>
                            <x-ui.data-table dense striped>
                                <caption class="visually-hidden">Services in Kategorie {{ $category }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Code</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Routing-Filter</th>
                                        <th scope="col">Payer</th>
                                        <th scope="col">Requirement</th>
                                        <th scope="col">Defaults</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $r)
                                        @php
                                            $req = $requirementVariant[$r['requirement']] ?? $requirementVariant['allowed'];
                                            $routingFilter = trim(($r['from_country'] ?? '—') . ' → ' . ($r['to_country'] ?? '—'));
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.settings.dhl.catalog.service.show', ['code' => $r['service_code']]) }}">
                                                    {{ $r['service_code'] }}
                                                </a>
                                            </td>
                                            <td>{{ $r['service_name'] }}</td>
                                            <td><span class="text-muted small">{{ $routingFilter }}</span></td>
                                            <td>{{ $r['payer_code'] ?? '—' }}</td>
                                            <td>
                                                <span class="badge {{ $req['class'] }} d-inline-flex align-items-center gap-1">
                                                    <i class="fa {{ $req['icon'] }} icon" aria-hidden="true"></i>
                                                    {{ $req['label'] }}
                                                </span>
                                            </td>
                                            <td>
                                                @if(! empty($r['default_parameters']))
                                                    <code class="small">{{ json_encode($r['default_parameters'], JSON_UNESCAPED_UNICODE) }}</code>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.data-table>
                        @endforeach
                    @endif
                </div>
            </section>
        @elseif($activeTab === 'audit' && $canViewAudit)
            <section id="dhl-product-tab-audit" role="tabpanel" aria-labelledby="tab-audit" class="card">
                <div class="card-body">
                    <x-ui.section-header
                        title="Audit (letzte 50)"
                        description="Letzte Aenderungen am Produkt-Code."
                    />

                    @if(count($auditEntries) === 0)
                        <x-ui.empty-state title="Keine Audit-Eintraege" />
                    @else
                        <x-ui.data-table dense>
                            <caption class="visually-hidden">Audit-Log fuer Produkt {{ $code }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Zeitstempel</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Actor</th>
                                    <th scope="col">Diff</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($auditEntries as $entry)
                                    <tr>
                                        <td>
                                            <time datetime="{{ $entry['created_at']->format(DATE_ATOM) }}">
                                                {{ $entry['created_at']->format('d.m.Y H:i') }}
                                            </time>
                                        </td>
                                        <td><span class="badge bg-light text-dark">{{ $entry['action'] }}</span></td>
                                        <td><span class="small">{{ $entry['actor'] }}</span></td>
                                        <td>
                                            <details>
                                                <summary class="small text-primary">JSON anzeigen</summary>
                                                <pre class="mb-0 small bg-light p-2 rounded">{{ json_encode($entry['diff'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.data-table>
                    @endif
                </div>
            </section>
        @endif
    </div>
@endsection
