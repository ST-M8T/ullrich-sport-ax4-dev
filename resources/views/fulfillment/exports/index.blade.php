@extends('layouts.admin', [
    'pageTitle' => $pageTitle ?? 'CSV-Export',
    'currentSection' => $currentSection ?? 'csv-export',
])


@section('content')
    <h1 class="mb-4">CSV-Export</h1>

    <p class="text-muted mb-4">Triggern Sie den CSV-Export für Fulfillment-Aufträge, laden Sie vorhandene Dateien herunter und behalten Sie Export-Jobs im Blick.</p>

    @if($recentExport)
        <div class="alert alert-success d-flex justify-content-between align-items-center">
            <div>
                <strong>Letzter Export:</strong>
                {{ $recentExport['orders_total'] ?? 0 }} Aufträge ·
                {{ $recentExport['file_path'] ?? '' }}
            </div>
            <a href="{{ route('csv-export.download', ['path' => $recentExport['file_token'] ?? '']) }}" class="btn btn-success btn-sm" download>
                Datei herunterladen
            </a>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header">Filter &amp; Übersicht</div>
        <div class="card-body">
            <x-filters.filter-form :action="route('csv-export')" :filters="$values ?? []" :hide-actions="true" class="row gy-3 gx-3 align-items-end">
                <x-forms.input
                    name="processed_from"
                    label="Verarbeitet ab"
                    type="date"
                    :value="$values['processed_from'] ?? ''"
                    col-class="col-md-3"
                />
                <x-forms.input
                    name="processed_to"
                    label="Verarbeitet bis"
                    type="date"
                    :value="$values['processed_to'] ?? ''"
                    col-class="col-md-3"
                />
                <x-forms.input
                    name="sender_code"
                    label="Sender-Code"
                    type="text"
                    :value="$values['sender_code'] ?? ''"
                    maxlength="64"
                    placeholder="z. B. DHL"
                    col-class="col-md-2"
                />
                <x-forms.input
                    name="destination_country"
                    label="Zielland"
                    type="text"
                    :value="$values['destination_country'] ?? ''"
                    maxlength="2"
                    placeholder="DE"
                    col-class="col-md-2"
                />
                <x-forms.select
                    name="is_booked"
                    label="Status"
                    :options="['' => 'Alle', '1' => 'Nur gebuchte', '0' => 'Nur ungebuchte']"
                    :value="(string)($values['is_booked'] ?? '')"
                    col-class="col-md-2"
                />
                <x-forms.select
                    name="job_status"
                    label="Job-Status"
                    :options="$jobStatusOptions ?? ['' => 'Alle', 'pending' => 'Ausstehend', 'running' => 'Laufend', 'completed' => 'Abgeschlossen', 'failed' => 'Fehlgeschlagen']"
                    :value="$jobStatus ?? ''"
                    col-class="col-md-2"
                />
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary text-uppercase">FILTER ANWENDEN</button>
                    <a href="{{ route('csv-export') }}" class="btn btn-outline-secondary text-uppercase">ZURÜCKSETZEN</a>
                </div>
            </x-filters.filter-form>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header">Export starten</div>
        <div class="card-body">
            <x-forms.form method="POST" action="{{ route('csv-export.trigger') }}">
                @foreach(['processed_from', 'processed_to', 'sender_code', 'destination_country', 'is_booked'] as $hidden)
                    @if(!empty($values[$hidden]))
                        <input type="hidden" name="{{ $hidden }}" value="{{ $values[$hidden] }}">
                    @endif
                @endforeach

                <x-forms.input
                    name="order_id"
                    label="Auftrags-ID (optional)"
                    type="number"
                    :value="old('order_id')"
                    min="1"
                    placeholder="Spezifische Order"
                    col-class="col-md-3"
                />
                <div class="col-md-6">
                    <p class="mb-0 text-muted small">Lässt das Feld leer, um alle Aufträge gemäß Filter zu exportieren. Bei Angabe der Auftrags-ID werden die Filter berücksichtigt.</p>
                </div>
                <div class="col-md-3 text-md-end">
                    <button type="submit" class="btn btn-success">Export ausführen</button>
                </div>
            </x-forms.form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Generierte Dateien</span>
                    <span class="badge bg-secondary">{{ count($files) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Datei</th>
                                    <th scope="col">Größe</th>
                                    <th scope="col">Erstellt</th>
                                    <th scope="col" class="text-end">Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($files as $file)
                                    <tr>
                                        <td>{{ $file['name'] }}</td>
                                        <td>{{ $formatSize($file['size'] ?? null) }}</td>
                                        <td>{{ $file['last_modified']?->format('d.m.Y H:i') ?? '—' }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('csv-export.download', ['path' => $file['download_token']]) }}" class="btn btn-outline-primary btn-sm" download>Download</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Noch keine Exportdateien vorhanden.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Export-Historie</span>
                    <span class="badge bg-secondary">{{ count($jobs) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Zeiten</th>
                                    <th scope="col">Ergebnis</th>
                                    <th scope="col" class="text-end">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($jobs as $job)
                                    <tr>
                                        <td>#{{ $job['id'] }}</td>
                                        <td>
                                            <span class="{{ $statusClasses[$job['status']] ?? 'badge bg-secondary' }} text-uppercase">{{ $job['status'] }}</span>
                                            @if(!empty($job['error']))
                                                <div class="text-danger small">{{ $job['error'] }}</div>
                                            @endif
                                        </td>
                                        <td class="small">
                                            <div><strong>Erstellt:</strong> {{ $job['created_at']->format('d.m.Y H:i') }}</div>
                                            @if($job['started_at'])
                                                <div><strong>Gestartet:</strong> {{ $job['started_at']->format('d.m.Y H:i') }}</div>
                                            @endif
                                            @if($job['finished_at'])
                                                <div><strong>Fertig:</strong> {{ $job['finished_at']->format('d.m.Y H:i') }}</div>
                                            @endif
                                        </td>
                                        <td class="small">
                                            <div><strong>Aufträge:</strong> {{ $job['orders_total'] ?? '—' }}</div>
                                            <div><strong>Datei:</strong> {{ $job['file'] ?? '—' }}</div>
                                            <div><strong>Größe:</strong> {{ $formatSize($job['file_size'] ?? null) }}</div>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                @if($job['download_token'])
                                                    <a href="{{ route('csv-export.download', ['path' => $job['download_token']]) }}" class="btn btn-outline-primary btn-sm" download>Download</a>
                                                @endif
                                                <form method="post" action="{{ route('csv-export.retry', ['job' => $job['id']]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Retry</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Keine Exporte vorhanden.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
