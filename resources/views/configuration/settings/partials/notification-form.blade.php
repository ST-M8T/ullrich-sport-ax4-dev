@php
    $notification = $notification ?? null;
    $isEdit = $notification !== null;
    $idSuffix = $isEdit ? 'edit_' . $notification->id()->toInt() : 'create';

    $channelOptions = [
        'mail' => 'Mail',
        'slack' => 'Slack',
        'sms' => 'SMS',
    ];
@endphp

<form method="post" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif
    <div class="row g-3">
        <x-forms.input
            name="notification_type"
            label="Typ"
            type="text"
            :value="old('notification_type', $notification?->notificationType() ?? '')"
            :required="true"
            placeholder="tracking-alert"
            col-class="col-md-4"
            :id-suffix="$idSuffix"
        />

        <x-forms.select
            name="channel"
            label="Channel"
            :options="$channelOptions"
            :value="old('channel', $notification?->channel())"
            placeholder="Standard (Mail)"
            col-class="col-md-4"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="template_key"
            label="Template-Key"
            type="text"
            :value="old('template_key')"
            placeholder="welcome"
            col-class="col-md-4"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="recipient"
            label="Empfänger"
            type="text"
            :value="old('recipient')"
            placeholder="mail@example.com"
            col-class="col-md-4"
            :id-suffix="$idSuffix"
        />

        <x-forms.input
            name="schedule_at"
            label="Geplant für"
            type="datetime-local"
            :value="old('schedule_at', $notification?->scheduledAt()?->format('Y-m-d\TH:i') ?? '')"
            col-class="col-md-4"
            :id-suffix="$idSuffix"
        />

        <x-forms.textarea
            name="payload"
            label="Payload (JSON)"
            :value="old('payload', $notification ? json_encode($notification->payload(), JSON_PRETTY_PRINT) : '')"
            :rows="4"
            placeholder='{&quot;template&quot;:&quot;welcome&quot;,&quot;to&quot;:&quot;mail@example.com&quot;}'
            col-class="col-12"
            :id-suffix="$idSuffix"
        />

        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm">{{ $isEdit ? 'Aktualisieren' : 'Benachrichtigung speichern' }}</button>
            @if(isset($cancelTarget))
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleCreateForm('{{ str_replace('#', '', $cancelTarget) }}')">Abbrechen</button>
            @endif
        </div>
    </div>
</form>
