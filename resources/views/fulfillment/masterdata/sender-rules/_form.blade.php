@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentSenderRule|null $rule */
    $rule = $rule ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';
    $senderProfiles = collect($senderProfiles ?? []);
    $ruleTypes = $ruleTypes ?? [];

    $fieldMappers = [
        'target_sender_id' => fn($r) => $r->targetSenderId()?->toInt(),
    ];

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $rule, $fieldMappers);

    $senderOptions = $senderProfiles->mapWithKeys(fn($s) => [
        $s->id()->toInt() => $s->displayName() . ' (' . $s->senderCode() . ')'
    ])->all();
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="priority"
        label="Priorität"
        type="number"
        :value="$value('priority', 100)"
        :required="true"
        min="0"
        col-class="col-md-2"
    />
    <x-forms.select
        name="rule_type"
        label="Regel-Typ"
        :options="$ruleTypes"
        :value="$value('rule_type')"
        :required="true"
        col-class="col-md-4"
    />
    <x-forms.input
        name="match_value"
        label="Vergleichswert"
        type="text"
        :value="$value('match_value')"
        :required="true"
        col-class="col-md-6"
    />
    <x-forms.select
        name="target_sender_id"
        label="Zielsender"
        :options="$senderOptions"
        :value="$value('target_sender_id')"
        :required="true"
        col-class="col-md-6"
    />
    <x-forms.input
        name="description"
        label="Beschreibung"
        type="text"
        :value="$value('description')"
        col-class="col-md-6"
    />
    <div class="col-12">
        <div class="form-check">
            <input
                id="is_active"
                class="form-check-input"
                type="checkbox"
                name="is_active"
                value="1"
                @checked($value('is_active', true))
            >
            <label class="form-check-label" for="is_active">Regel aktiv</label>
        </div>
    </div>

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.sender-rules.index')"
        />
    </x-slot>
</x-forms.form>
