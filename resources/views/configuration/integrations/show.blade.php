@extends('layouts.admin', [
    'pageTitle' => $provider->name(),
    'currentSection' => 'configuration-integrations',
])

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="mb-0">{{ $provider->name() }}</h1>
            <p class="text-muted mb-0">{{ $provider->description() }}</p>
        </div>
        <a href="{{ route('configuration-integrations') }}" class="btn btn-outline-secondary">
            Zurück zur Übersicht
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <section class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Konfiguration</h2>
        </div>
        <form method="post" action="{{ route('configuration-integrations.update', ['integrationKey' => $provider->key()]) }}">
            @csrf

            <div class="card-body">
                <div class="row g-4">
                    @foreach($processedFields as $fieldKey => $processed)
                        <div class="col-md-6">
                            @if($processed['type'] === 'select')
                                <x-forms.select
                                    name="configuration[{{ $fieldKey }}]"
                                    :label="$processed['label']"
                                    :options="$processed['options']"
                                    :value="(string) $processed['currentValue']"
                                    :required="$processed['isRequired']"
                                    placeholder="— Auswahl —"
                                    col-class="col-12"
                                />
                            @elseif($processed['type'] === 'textarea')
                                <x-forms.textarea
                                    name="configuration[{{ $fieldKey }}]"
                                    :label="$processed['label']"
                                    :value="$processed['processedValue']"
                                    :placeholder="$processed['placeholder']"
                                    :required="$processed['isRequired']"
                                    rows="4"
                                    col-class="col-12"
                                />
                            @elseif($processed['type'] === 'checkbox')
                                <input type="hidden" name="configuration[{{ $fieldKey }}]" value="0">
                                <x-forms.checkbox
                                    name="configuration[{{ $fieldKey }}]"
                                    label="Aktiviert"
                                    :checked="$processed['isChecked']"
                                    col-class="col-12"
                                />
                            @elseif($processed['type'] === 'number')
                                <x-forms.input
                                    name="configuration[{{ $fieldKey }}]"
                                    :label="$processed['label']"
                                    type="number"
                                    :value="$processed['currentValue']"
                                    :placeholder="$processed['placeholder']"
                                    :required="$processed['isRequired']"
                                    col-class="col-12"
                                />
                            @else
                                <x-forms.input
                                    name="configuration[{{ $fieldKey }}]"
                                    :label="$processed['label']"
                                    :type="$processed['inputType']"
                                    :value="$processed['processedValue']"
                                    :placeholder="$processed['placeholder']"
                                    :required="$processed['isRequired']"
                                    col-class="col-12"
                                />
                            @endif

                            @if($processed['help'])
                                <small class="form-text text-muted">{{ $processed['help'] }}</small>
                            @endif

                            @if($processed['showSecretHint'])
                                <small class="form-text text-muted d-block mt-1">
                                    Aktuell gesetzt – leer lassen, um den Wert zu behalten.
                                </small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" name="action" value="test" formaction="{{ route('configuration-integrations.test', ['integrationKey' => $provider->key()]) }}" class="btn btn-outline-secondary">
                    Verbindung testen
                </button>
                <button type="submit" class="btn btn-primary">
                    Speichern
                </button>
            </div>
        </form>
    </section>
@endsection
