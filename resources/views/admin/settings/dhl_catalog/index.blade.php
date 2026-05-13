@extends('layouts.admin', [
    'pageTitle' => 'DHL Katalog',
    'currentSection' => 'admin-settings-dhl-catalog',
])

{{--
    DHL Katalog — Read-Only Uebersicht (PROJ-6 / t16).

    Engineering-Handbuch:
      §7  Presentation only — Controller liefert fertige View-Daten.
      §40 Semantisches HTML, keine Inline-Scripts.
      §51 Accessibility: <th scope>, <label for>, Status nicht nur per Farbe.
      §53 Loading/Empty/Error: Empty-State + Sync-Banner.
      §75 DRY:    Wiederverwendung von x-dhl.catalog-sync-banner,
                  x-dhl.catalog-status-badge, x-ui.* Komponenten.
--}}

@php
    /** @var \App\Domain\Shared\ValueObjects\Pagination\PaginatedResult $products */
    /** @var \App\Domain\Fulfillment\Shipping\Dhl\Catalog\DhlCatalogSyncStatus $syncStatus */
    /** @var array<string,mixed> $filter */
    /** @var bool $canSync */
    /** @var bool $canViewAudit */

    $items = $products->items();
    $total = $products->total();

    $fromSelected = $filter['from_country'] ?? [];
    $toSelected   = $filter['to_country'] ?? [];
    $statusValue  = $filter['status'] ?? '';
    $sourceValue  = $filter['source'] ?? '';
    $qValue       = $filter['q'] ?? '';

    // Country-Options aus den aktuell selektierten + EU-Default-Set.
    $defaultCountries = ['DE','AT','CH','FR','IT','NL','BE','PL','DK','SE','ES','GB','CZ','HU','LU'];
    $fromOptions = collect($defaultCountries)->merge($fromSelected)->unique()->sort()->values()->all();
    $toOptions   = collect($defaultCountries)->merge($toSelected)->unique()->sort()->values()->all();

    $statusOptions = [
        ''           => 'Alle Status',
        'active'     => 'Aktiv',
        'deprecated' => 'Deprecated',
    ];
    $sourceOptions = [
        ''       => 'Alle Quellen',
        'seed'   => 'Seed',
        'api'    => 'API',
        'manual' => 'Manuell',
    ];

    $syncStatusUrl = route('admin.settings.dhl.catalog.sync.status');
@endphp

@section('content')
    <div class="admin-content">
        <x-ui.page-header
            title="DHL Katalog"
            subtitle="Read-Only-Ansicht der per Sync gepflegten DHL Freight Produkte und Additional Services."
        >
            <x-slot:actions>
                @if($canViewAudit)
                    <a
                        href="{{ route('admin.settings.dhl.catalog.audit.index') }}"
                        class="btn btn-outline-secondary"
                    >
                        <i class="fa fa-history icon" aria-hidden="true"></i>
                        Audit-Log
                    </a>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <x-dhl.catalog-sync-banner
            :sync-status="$syncStatus"
            :can-sync="$canSync"
            :trigger-url="route('admin.settings.dhl.catalog.sync.trigger')"
        />

        {{-- Filter --}}
        <section class="card mb-4" aria-labelledby="dhl-catalog-filter-heading">
            <div class="card-body">
                <h2 class="h6 mb-3" id="dhl-catalog-filter-heading">Filter</h2>
                <form
                    method="get"
                    action="{{ route('admin.settings.dhl.catalog.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-3">
                        <label for="dhl-catalog-filter-q" class="form-label">Suche (Code / Name)</label>
                        <input
                            type="text"
                            id="dhl-catalog-filter-q"
                            name="q"
                            value="{{ $qValue }}"
                            maxlength="64"
                            class="form-control"
                            placeholder="z.B. ECI"
                        >
                    </div>

                    <div class="col-md-2">
                        <label for="dhl-catalog-filter-status" class="form-label">Status</label>
                        <select id="dhl-catalog-filter-status" name="status" class="form-select">
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="dhl-catalog-filter-source" class="form-label">Quelle</label>
                        <select id="dhl-catalog-filter-source" name="source" class="form-select">
                            @foreach($sourceOptions as $value => $label)
                                <option value="{{ $value }}" @selected($sourceValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="dhl-catalog-filter-from" class="form-label">Von-Land</label>
                        <select id="dhl-catalog-filter-from" name="from_country[]" multiple size="4" class="form-select">
                            @foreach($fromOptions as $cc)
                                <option value="{{ $cc }}" @selected(in_array($cc, $fromSelected, true))>{{ $cc }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="dhl-catalog-filter-to" class="form-label">Nach-Land</label>
                        <select id="dhl-catalog-filter-to" name="to_country[]" multiple size="4" class="form-select">
                            @foreach($toOptions as $cc)
                                <option value="{{ $cc }}" @selected(in_array($cc, $toSelected, true))>{{ $cc }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter icon" aria-hidden="true"></i> Filtern
                        </button>
                        <a href="{{ route('admin.settings.dhl.catalog.index') }}" class="btn btn-outline-secondary">
                            Zuruecksetzen
                        </a>
                    </div>
                </form>
            </div>
        </section>

        {{-- Ergebnis-Tabelle --}}
        <section class="card" aria-labelledby="dhl-catalog-products-heading">
            <div class="card-body">
                <x-ui.section-header
                    title="Produkte"
                    :count="$total"
                    description="Liste aller im System hinterlegten DHL Freight Produkte."
                >
                </x-ui.section-header>

                <p class="text-muted small mb-3" data-products-total>
                    Produkte: {{ $total }}
                </p>

                @if($total === 0)
                    <x-ui.empty-state
                        title="Keine Produkte gefunden"
                        description="Passe die Filter an oder starte einen DHL-Katalog-Sync."
                    />
                @else
                    <x-ui.data-table hover>
                        <caption class="visually-hidden">Liste der DHL Freight Produkte</caption>
                        <thead>
                            <tr>
                                <th scope="col">Code</th>
                                <th scope="col">Name</th>
                                <th scope="col">Routings</th>
                                <th scope="col">Status</th>
                                <th scope="col">Quelle</th>
                                <th scope="col">Letzter Sync</th>
                                <th scope="col" class="text-end"># Services</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $row)
                                @php
                                    $routingPairs = [];
                                    foreach ($row['from_countries'] as $from) {
                                        foreach ($row['to_countries'] as $to) {
                                            $routingPairs[] = $from . '→' . $to;
                                        }
                                    }
                                    $routingsShort = implode(', ', array_slice($routingPairs, 0, 3))
                                        . (count($routingPairs) > 3 ? sprintf(' (+%d)', count($routingPairs) - 3) : '');
                                    $routingsFull = implode(', ', $routingPairs);
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.settings.dhl.catalog.product.show', ['code' => $row['code']]) }}"
                                           class="fw-semibold">
                                            {{ $row['code'] }}
                                        </a>
                                    </td>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        <span title="{{ $routingsFull }}" aria-label="Routings: {{ $routingsFull }}">
                                            {{ $routingsShort ?: '—' }}
                                        </span>
                                    </td>
                                    <td>
                                        <x-dhl.catalog-status-badge :status="$row['status']" />
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">{{ $row['source'] }}</span>
                                    </td>
                                    <td>
                                        @if($row['synced_at'] !== null)
                                            <time datetime="{{ $row['synced_at']->format(DATE_ATOM) }}">
                                                {{ $row['synced_at']->format('d.m.Y H:i') }}
                                            </time>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $row['services_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>

                    <x-ui.pagination-footer :paginator="$productsLinks" label="Produkten" />
                @endif
            </div>
        </section>
    </div>

    {{-- Sync-Polling-Hook (JS-Modul; Engineering-Handbuch §40: kein Inline-Script). --}}
    <div
        data-dhl-catalog-sync-poll
        data-status-url="{{ $syncStatusUrl }}"
        data-interval-ms="5000"
        hidden
    ></div>
@endsection
