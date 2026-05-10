@extends('layouts.admin', [
    'pageTitle' => 'Benachrichtigungen',
    'currentSection' => 'configuration-notifications',
])

@section('content')
    <h1 class="mb-4">Benachrichtigungen</h1>

    <div class="card mb-4">
        <div class="card-header">
            Channel-Einstellungen
        </div>
        <div class="card-body">
            <fieldset>
                <legend class="visually-hidden">Channel-Einstellungen</legend>
            <div class="row g-4">
                @foreach($channelSettings as $channel)
                    <div class="col-12 col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-semibold">{{ $channel['label'] }}</span>
                                @if($channel['enabled'])
                                    <span class="badge bg-success">Aktiv</span>
                                @else
                                    <span class="badge bg-secondary">Inaktiv</span>
                                @endif
                            </div>
                            @foreach($channel['fields'] as $field)
                                @if($field['type'] === 'checkbox')
                                    <x-forms.checkbox
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :checked="old($field['errorKey'], $field['value'])"
                                        col-class="col-12"
                                    />
                                @else
                                    <x-forms.input
                                        :name="$field['name']"
                                        :label="$field['label']"
                                        :type="$field['type']"
                                        :value="old($field['errorKey'], $field['value'])"
                                        :placeholder="$field['placeholder'] ?? null"
                                        col-class="col-12"
                                    />
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            </fieldset>
            <div class="row">
                <div class="col-12">
                    <x-forms.form-actions submit-label="Einstellungen speichern" />
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            Manuelle Benachrichtigung erstellen
        </div>
        <div class="card-body">
            <x-forms.form method="POST" action="{{ route('configuration-notifications.store') }}">
                <x-forms.input
                    name="notification_type"
                    label="Typ"
                    type="text"
                    :value="old('notification_type')"
                    required
                    col-class="col-md-4"
                />
                <x-forms.select
                    name="channel"
                    label="Channel"
                    :options="collect($availableChannels)->mapWithKeys(fn($ch) => [$ch['key'] => $ch['label'] . (!$ch['enabled'] ? ' (deaktiviert)' : '')])->prepend('Standard (Mail)', '')->all()"
                    :value="old('channel')"
                    col-class="col-md-4"
                />
                <x-forms.input
                    name="template_key"
                    label="Template-Key"
                    type="text"
                    :value="old('template_key')"
                    col-class="col-md-4"
                />
                <x-forms.input
                    name="recipient"
                    label="Empfänger"
                    type="text"
                    :value="old('recipient')"
                    col-class="col-md-4"
                />
                <x-forms.input
                    name="schedule_at"
                    label="Geplant für"
                    type="datetime-local"
                    :value="old('schedule_at')"
                    col-class="col-md-4"
                />
                <x-forms.textarea
                    name="payload"
                    label="Payload (JSON)"
                    :value="old('payload')"
                    placeholder='{"template":"welcome","to":"mail@example.com"}'
                    rows="4"
                    col-class="col-12"
                />
                <x-forms.form-actions submit-label="Benachrichtigung speichern" />
            </x-forms.form>
        </div>
    </div>

    <x-filters.filter-form :action="route('configuration-notifications')" :filters="$filters ?? []" class="mb-4">
        <x-forms.input
            name="notification_type"
            label="Typ"
            type="text"
            :value="$filters['notification_type'] ?? ''"
            placeholder="tracking-alert"
            col-class="col-md-4"
        />
        <x-forms.input
            name="status"
            label="Status"
            type="text"
            :value="$filters['status'] ?? ''"
            placeholder="pending"
            col-class="col-md-4"
        />
        <x-forms.input
            name="limit"
            label="Limit"
            type="number"
            :value="$limit ?? 50"
            min="10"
            max="200"
            col-class="col-md-2"
        />
        <x-forms.input
            name="offset"
            label="Offset"
            type="number"
            :value="$offset ?? 0"
            min="0"
            col-class="col-md-2"
        />
    </x-filters.filter-form>

    <form method="post" action="{{ route('configuration-notifications.dispatch') }}" class="d-flex gap-2 mb-4">
        @csrf
        <div class="input-group input-group-max-width">
            <span class="input-group-text">Limit</span>
            <input type="number" name="limit" value="50" min="1" max="200" class="form-control">
        </div>
        <button type="submit" class="btn btn-success">Pending Benachrichtigungen senden</button>
    </form>

    <div class="table-responsive">
        <x-ui.data-table dense striped>
            <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Typ</th>
                <th scope="col">Channel</th>
                <th scope="col">Status</th>
                <th scope="col">Geplant</th>
                <th scope="col">Gesendet</th>
                <th scope="col">Fehler</th>
                <th scope="col" class="text-end">Aktionen</th>
            </tr>
            </thead>
            <tbody>
            @forelse($notifications as $notification)
                <tr>
                    <td>{{ $notification->id()->toInt() }}</td>
                    <td>{{ $notification->notificationType() }}</td>
                    <td>{{ $notification->channel() ?? '—' }}</td>
                    <td><span class="badge bg-secondary text-uppercase" aria-label="Status: {{ $notification->status() }}">{{ $notification->status() }}</span></td>
                    <td>{{ $notification->scheduledAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $notification->sentAt()?->format('d.m.Y H:i') ?? '—' }}</td>
                    <td>{{ $notification->errorMessage() ?? '—' }}</td>
                    <td class="text-end">
                        <form method="post" action="{{ route('configuration-notifications.redispatch', ['notification' => $notification->id()->toInt()]) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                Erneut senden
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">Keine Benachrichtigungen gefunden.</td>
                </tr>
            @endforelse
            </tbody>
        </x-ui.data-table>
    </div>
@endsection
