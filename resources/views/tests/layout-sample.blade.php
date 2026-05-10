{{-- Test-Fixture für AdminLayoutSnapshotTest. NICHT geroutet, dient ausschließlich
     der Verifikation des Admin-Layouts mit Standard-UI-Komponenten in
     tests/Feature/Layout/AdminLayoutSnapshotTest.php. --}}
@extends('layouts.admin', ['title' => 'Layout-Sample'])

@section('content')
    <x-flash-messages :messages="$messages" :success="$success ?? null" />

    <x-ui.action-card title="Aktions-Karte" description="Beispiel-Layout mit Slot.">
        <x-slot:actions>
            <button class="btn btn--primary" type="button">Speichern</button>
        </x-slot:actions>
        <p>Inhalt einer Aktions-Karte für den Layout-Test.</p>
    </x-ui.action-card>

    <x-ui.info-card title="Info-Karte">
        <p>Beispiel-Info für den Snapshot-Vergleich.</p>
    </x-ui.info-card>

    <x-ui.empty-state
        title="Keine Daten"
        description="Es liegen aktuell keine Daten vor." />

    @if(! empty($showSpinner))
        <x-ui.spinner :message="$spinnerMessage ?? 'Lade...'" />
    @endif
@endsection
