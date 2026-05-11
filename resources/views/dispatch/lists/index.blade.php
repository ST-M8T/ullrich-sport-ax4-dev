@extends('layouts.admin', [
    'pageTitle' => 'Kommissionierlisten',
    'currentSection' => 'dispatch-lists',
])


@section('content')
    <div class="stack stack-lg">
        <div class="stack stack-xs">
            <h1 class="mb-0">Kommissionierlisten</h1>
            <p class="text-muted mb-0">Verwaltung und Übersicht über alle Versandlisten für DHL Freight.</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="alert">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <p class="mb-1 fw-semibold">Aktion konnte nicht abgeschlossen werden:</p>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <section class="card">
            <div class="card-header d-flex flex-wrap gap-3 justify-content-between align-items-start">
                <div>
                    <h2 class="h5 mb-1">Versandlisten</h2>
                    <p class="text-muted small mb-0">
                        {{ trans_choice('{0} Keine Listen|{1} :count Liste|[2,*] :count Listen', $totalLists, ['count' => $totalLists]) }}
                    </p>
                </div>
                <x-filters.filter-form :action="route('dispatch-lists')" :filters="$filters ?? []" class="d-flex flex-wrap gap-2 align-items-end">
                    <x-forms.select
                        name="status"
                        label="Status"
                        :options="$statusOptions"
                        :value="$filters['status'] ?? ''"
                        class="form-select-sm"
                        col-class="stack stack-xs"
                    />
                    <x-forms.input
                        name="reference"
                        label="Referenz"
                        type="text"
                        :value="$filters['reference'] ?? ''"
                        placeholder="Referenz enthält …"
                        class="form-control-sm"
                        col-class="stack stack-xs"
                    />
                    <x-forms.input
                        name="per_page"
                        label="pro Seite"
                        type="number"
                        :value="$perPage ?? 25"
                        min="5"
                        max="100"
                        class="form-control-sm"
                        col-class="stack stack-xs"
                    />
                </x-filters.filter-form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-hover table-sm align-middle">
            <thead>
            <tr>
                <th scope="col" class="text-uppercase">ID</th>
                <th scope="col" class="text-uppercase">REFERENZ</th>
                <th scope="col" class="text-uppercase">STATUS</th>
                <th scope="col" class="text-uppercase">METRIKEN</th>
                <th scope="col" class="text-uppercase">SCANS</th>
                <th scope="col" class="text-uppercase">ZEITEN</th>
                <th scope="col" class="text-uppercase">NOTIZEN</th>
                <th scope="col" class="text-uppercase">AKTIONEN</th>
            </tr>
            </thead>
            <tbody>
            @forelse($processedLists as $processed)
                <tr>
                    <td>
                        <strong>#{{ (string) $processed['list']->id() }}</strong><br>
                        <small class="text-muted">Erstellt {{ $processed['list']->createdAt()->format('d.m.Y H:i') }}</small>
                    </td>
                    <td>
                        {{ $processed['list']->reference() ?? '—' }}<br>
                        <small class="text-muted">{{ $processed['list']->title() ?? 'Ohne Titel' }}</small>
                    </td>
                    <td>
                        <span class="badge text-uppercase" data-tone="{{ $processed['statusTone'] }}" aria-label="Status: {{ $processed['status'] }}">{{ $processed['status'] }}</span><br>
                        @if($processed['list']->closeRequestedAt())
                            <small class="text-muted">Schließanforderung {{ $processed['list']->closeRequestedAt()?->format('d.m.Y H:i') }}</small><br>
                        @endif
                        @if($processed['list']->closedAt())
                            <small class="text-muted">Geschlossen {{ $processed['list']->closedAt()?->format('d.m.Y H:i') }}</small><br>
                        @endif
                        @if($processed['list']->exportedAt())
                            <small class="text-muted">Exportiert {{ $processed['list']->exportedAt()?->format('d.m.Y H:i') }}</small>
                        @endif
                    </td>
                    <td>
                        <button type="button"
                                class="btn btn-outline-primary btn-sm text-uppercase"
                                data-bs-toggle="modal"
                                data-bs-target="#{{ $processed['metricsModalId'] }}">
                            METRIKEN
                        </button>
                    </td>
                    <td>
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm text-uppercase"
                                data-dispatch-scans-trigger
                                data-fetch-url="{{ route('dispatch-lists.scans', ['list' => $processed['listId']]) }}"
                                data-dispatch-label="#{{ $processed['listId'] }}"
                                @if($processed['scanCount'] === 0) disabled @endif>
                            SCANS ({{ $processed['scanCount'] }})
                        </button>
                    </td>
                    <td>
                        <small class="text-muted">
                            Zuletzt aktualisiert {{ $processed['list']->updatedAt()->format('d.m.Y H:i') }}
                        </small><br>
                        @if($processed['list']->exportFilename())
                            <small class="text-muted">Export: {{ $processed['list']->exportFilename() }}</small>
                        @endif
                    </td>
                    <td>{{ $processed['list']->notes() ?? '—' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button"
                                    class="btn btn-warning btn-sm text-uppercase"
                                    data-bs-toggle="modal"
                                    data-bs-target="#{{ $processed['closeModalId'] }}"
                                    @if($processed['closeDisabled']) disabled title="Liste bereits geschlossen oder exportiert" @endif>
                                SCHLIESSEN
                            </button>
                            <button type="button"
                                    class="btn btn-success btn-sm text-uppercase"
                                    data-bs-toggle="modal"
                                    data-bs-target="#{{ $processed['exportModalId'] }}"
                                    @if($processed['exportDisabled']) disabled title="Export erst möglich, nachdem die Liste geschlossen wurde" @endif>
                                EXPORT
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <x-ui.empty-state
                            title="Keine Dispatch-Listen vorhanden"
                            description="Es wurden keine Versandlisten gefunden. Erstellen Sie eine neue Liste oder prüfen Sie die Filtereinstellungen."
                        />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($totalLists === 0)
        <x-ui.empty-state
            title="Keine Versandlisten synchronisiert"
            description="Es wurden keine Versandlisten gefunden. Prüfen Sie Import-Jobs oder Filtereinstellungen."
        />
    @endif
            </div>
        </section>
    </div>

    <nav aria-label="Pagination" class="mt-3">
        <ul class="pagination">
            <li class="page-item @if($page === 1) disabled @endif">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => max(1, $page - 1)]) }}">Zurück</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Seite {{ $page }} / {{ $totalPages }}</span>
            </li>
            <li class="page-item @if(!$pagination->hasMorePages()) disabled @endif">
                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $page + 1]) }}">Weiter</a>
            </li>
        </ul>
    </nav>

    @foreach($processedModals as $processed)

        <div class="modal fade" id="{{ $processed['metricsModalId'] }}" tabindex="-1" aria-labelledby="{{ $processed['metricsModalId'] }}-label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="{{ $processed['metricsModalId'] }}-label">Metriken für Liste #{{ $processed['listId'] }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted">Aufträge</dt>
                            <dd class="col-sm-8 mb-2"><strong>{{ $processed['metrics']->totalOrders() }}</strong></dd>
                            <dt class="col-sm-4 text-muted">Pakete</dt>
                            <dd class="col-sm-8 mb-2"><strong>{{ $processed['metrics']->totalPackages() }}</strong></dd>
                            <dt class="col-sm-4 text-muted">Artikel</dt>
                            <dd class="col-sm-8 mb-2"><strong>{{ $processed['metrics']->totalItems() }}</strong></dd>
                            <dt class="col-sm-4 text-muted">Truck Slots</dt>
                            <dd class="col-sm-8 mb-2"><strong>{{ $processed['metrics']->totalTruckSlots() }}</strong></dd>
                        </dl>
                        @if(!empty($processed['metrics']->metrics()))
                            <hr>
                            <h3 class="fs-6">Weitere Details</h3>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                    @foreach($processed['metrics']->metrics() as $key => $value)
                                        <tr>
                                            <th scope="row" class="text-nowrap">{{ $key }}</th>
                                            <td>
                                                @if(is_array($value))
                                                    <pre class="mb-0 small">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                @else
                                                    {{ is_bool($value) ? ($value ? 'Ja' : 'Nein') : (string) $value }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm text-uppercase" data-bs-dismiss="modal">SCHLIESSEN</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="{{ $processed['closeModalId'] }}" tabindex="-1" aria-labelledby="{{ $processed['closeModalId'] }}-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('dispatch-lists.close', ['list' => $processed['listId']]) }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="{{ $processed['closeModalId'] }}-label">Dispatch-Liste schließen (#{{ $processed['listId'] }})</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Durch das Schließen werden weitere Scans verhindert und die Liste als abgeschlossen markiert.</p>
                            <x-forms.input
                                name="export_filename"
                                label="Export-Dateiname (optional)"
                                :value="old('export_filename', 'dispatch-list-' . $processed['listId'] . '.csv')"
                                placeholder="dispatch-list-{{ $processed['listId'] }}.csv"
                                col-class="col-12"
                            >
                                <x-slot name="help">Dateiname muss auf <code>.csv</code> enden.</x-slot>
                            </x-forms.input>
                            @foreach(['status', 'reference'] as $filterKey)
                                @if(!empty($filters[$filterKey]))
                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filters[$filterKey] }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-uppercase" data-bs-dismiss="modal">ABBRECHEN</button>
                            <button type="submit" class="btn btn-warning btn-sm text-uppercase">LISTE SCHLIESSEN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="{{ $processed['exportModalId'] }}" tabindex="-1" aria-labelledby="{{ $processed['exportModalId'] }}-label" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('dispatch-lists.export', ['list' => $processed['listId']]) }}">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="{{ $processed['exportModalId'] }}-label">Dispatch-Liste exportieren (#{{ $processed['listId'] }})</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Beim Export wird die Liste als <strong>exportiert</strong> markiert und der angegebene Dateiname gespeichert.</p>
                            <x-forms.input
                                name="export_filename"
                                label="Export-Dateiname"
                                :value="old('export_filename', 'dispatch-list-' . $processed['listId'] . '.csv')"
                                placeholder="dispatch-list-{{ $processed['listId'] }}.csv"
                                required
                                col-class="col-12"
                            >
                                <x-slot name="help">Dateiname muss auf <code>.csv</code> enden.</x-slot>
                            </x-forms.input>
                            @foreach(['status', 'reference'] as $filterKey)
                                @if(!empty($filters[$filterKey]))
                                    <input type="hidden" name="{{ $filterKey }}" value="{{ $filters[$filterKey] }}">
                                @endif
                            @endforeach
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-uppercase" data-bs-dismiss="modal">ABBRECHEN</button>
                            <button type="submit" class="btn btn-success btn-sm text-uppercase">EXPORT STARTEN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="dispatch-scans-modal" tabindex="-1" aria-labelledby="dispatch-scans-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="dispatch-scans-modal-label">Scans</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="dispatch-scans-modal-body">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Lädt …</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary text-uppercase" data-bs-dismiss="modal">SCHLIESSEN</button>
                </div>
            </div>
        </div>
    </div>

@endsection
