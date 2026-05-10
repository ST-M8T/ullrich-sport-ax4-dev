<x-forms.form :action="$action" :method="$method === 'POST' ? null : $method" :novalidate="false">
    <x-forms.input
        name="setting_key"
        label="Key"
        type="text"
        :value="$settingKeyValue"
        :required="true"
        :readonly="$isEdit"
        col-class="col-12"
    />
    <x-forms.select
        name="value_type"
        label="Wert-Typ"
        :options="array_combine($valueTypes, $valueTypes)"
        :value="$valueTypeValue"
        :required="true"
        col-class="col-12"
    />
    <x-forms.textarea
        name="setting_value"
        label="Wert"
        :value="$settingValueValue"
        :rows="4"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('configuration-settings')"
        />
    </x-slot>
</x-forms.form>
