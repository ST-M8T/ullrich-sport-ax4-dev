@extends('layouts.admin', [
    'pageTitle' => 'DHL Katalog Audit-Log',
    'currentSection' => 'admin-settings-dhl-catalog',
])

{{--
    Audit-Log (PROJ-6 / t16). Read-Only.

    §7  Presentation only.
    §51 Accessibility: <th scope>, <label for>, <dialog> mit aria-modal.
    §75 Wiederverwendete UI-Komponenten.
--}}

@php
    /** @var \App\Domain\Shared\ValueObjects\Pagination\PaginatedResult $entries */
    /** @var array<string,mixed> $filter */

    $items = $entries->items();
    $total = $entries->total();

    $entityTypeOptions = [
        ''           => 'Alle Entitaeten',
        'product'    => 'Produkt',
        'service'    => 'Service',
        'assignment' => 'Assignment',
    ];
    $actionOptions = [
        ''           => 'Alle Aktionen',
        'created'    => 'Erstellt',
        'updated'    => 'Aktualisiert',
        'deprecated' => 'Deprecated',
        'restored'   => 'Wiederhergestellt',
        'deleted'    => 'Geloescht',
    ];

    $actionVariant = [
        'created'    => 'bg-success-subtle text-success-emphasis',
        'updated'    => 'bg-info-subtle text-info-emphasis',
        'deprecated' => 'bg-warning-subtle text-warning-emphasis',
        'restored'   => 'bg-primary-subtle text-primary-emphasis',
        'deleted'    => 'bg-danger-subtle text-danger-emphasis',
    ];
@endphp

@section('content')
    <div class="admin-content">

        <x-ui.page-header
            title="DHL Katalog Audit-Log"
            subtitle="Chronologische Aufzeichnung aller Aenderungen am Produktkatalog."
        >
            <x-slot:actions>
                <a href="{{ route('admin.settings.dhl.catalog.index') }}" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left icon" aria-hidden="true"></i> Zurueck zur Uebersicht
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filter --}}
        <section class="card mb-4" aria-labelledby="dhl-audit-filter-heading">
            <div class="card-body">
                <h2 class="h6 mb-3" id="dhl-audit-filter-heading">Filter</h2>
                <form
                    method="get"
                    action="{{ route('admin.settings.dhl.catalog.audit.index') }}"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-3">
                        <label for="dhl-audit-from" class="form-label">Von</label>
                        <input type="datetime-local" id="dhl-audit-from" name="from"
                               value="{{ $filter['from'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="dhl-audit-to" class="form-label">Bis</label>
                        <input type="datetime-local" id="dhl-audit-to" name="to"
                               value="{{ $filter['to'] ?? '' }}" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label for="dhl-audit-entity-type" class="form-label">Entitaet</label>
                        <select id="dhl-audit-entity-type" name="entity_type" class="form-select">
                            @foreach($entityTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filter['entity_type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="dhl-audit-action" class="form-label">Aktion</label>
                        <select id="dhl-audit-action" name="action" class="form-select">
                            @foreach($actionOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filter['action'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="dhl-audit-actor" class="form-label">Actor</label>
                        <input type="text" id="dhl-audit-actor" name="actor"
                               value="{{ $filter['actor'] ?? '' }}" maxlength="128"
                               class="form-control" placeholder="user:…">
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter icon" aria-hidden="true"></i> Filtern
                        </button>
                        <a href="{{ route('admin.settings.dhl.catalog.audit.index') }}" class="btn btn-outline-secondary">
                            Zuruecksetzen
                        </a>
                    </div>
                </form>
            </div>
        </section>

        {{-- Tabelle --}}
        <section class="card" aria-labelledby="dhl-audit-results-heading">
            <div class="card-body">
                <x-ui.section-header
                    title="Eintraege"
                    :count="$total"
                />

                <p class="text-muted small mb-3" data-entries-total>
                    Einträge: {{ $total }}
                </p>

                @if($total === 0)
                    <x-ui.empty-state
                        title="Keine Audit-Eintraege gefunden"
                        description="Passe die Filter an oder warte auf neue Katalog-Aenderungen."
                    />
                @else
                    <x-ui.data-table hover striped>
                        <caption class="visually-hidden">Audit-Log Eintraege</caption>
                        <thead>
                            <tr>
                                <th scope="col">Zeitstempel</th>
                                <th scope="col">Entitaet</th>
                                <th scope="col">Key</th>
                                <th scope="col">Aktion</th>
                                <th scope="col">Actor</th>
                                <th scope="col">Diff</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $entry)
                                @php
                                    $diffJson = json_encode($entry['diff'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                    $variantClass = $actionVariant[$entry['action']] ?? 'bg-light text-dark';
                                    $modalId = 'dhl-audit-diff-' . $entry['id'];
                                @endphp
                                <tr>
                                    <td>
                                        <time datetime="{{ $entry['created_at']->format(DATE_ATOM) }}">
                                            {{ $entry['created_at']->format('d.m.Y H:i:s') }}
                                        </time>
                                    </td>
                                    <td>{{ $entry['entity_type'] }}</td>
                                    <td><code class="small">{{ $entry['entity_key'] }}</code></td>
                                    <td>
                                        <span class="badge {{ $variantClass }} d-inline-flex align-items-center gap-1">
                                            <i class="fa fa-tag icon" aria-hidden="true"></i>
                                            {{ $entry['action'] }}
                                        </span>
                                    </td>
                                    <td><span class="small">{{ $entry['actor'] }}</span></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-dhl-audit-diff-trigger
                                            data-dialog-target="{{ $modalId }}"
                                            aria-haspopup="dialog"
                                        >
                                            <i class="fa fa-eye icon" aria-hidden="true"></i>
                                            Diff
                                        </button>
                                        <dialog
                                            id="{{ $modalId }}"
                                            class="dhl-audit-diff-dialog"
                                            aria-labelledby="{{ $modalId }}-title"
                                        >
                                            <div class="d-flex justify-content-between align-items-center mb-2 gap-3">
                                                <h3 id="{{ $modalId }}-title" class="h6 mb-0">
                                                    Diff #{{ $entry['id'] }} — {{ $entry['entity_type'] }} / {{ $entry['entity_key'] }}
                                                </h3>
                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-dhl-audit-diff-close
                                                    aria-label="Dialog schliessen"
                                                ></button>
                                            </div>
                                            <pre class="small bg-light p-3 rounded mb-0">{{ $diffJson }}</pre>
                                        </dialog>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.data-table>

                    <x-ui.pagination-footer :paginator="$entriesLinks" label="Eintraegen" />
                @endif
            </div>
        </section>
    </div>
@endsection
