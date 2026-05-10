@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentPackagingProfile|null $profile */
    $profile = $profile ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $profile);
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="package_name"
        label="Bezeichnung"
        type="text"
        :value="$value('package_name')"
        :required="true"
        col-class="col-md-6"
    />
    <x-forms.input
        name="packaging_code"
        label="Profil-Code"
        type="text"
        :value="$value('packaging_code')"
        col-class="col-md-6"
    />
    <x-forms.input
        name="length_mm"
        label="Länge (mm)"
        type="number"
        :value="$value('length_mm')"
        :required="true"
        min="1"
        col-class="col-md-4"
    />
    <x-forms.input
        name="width_mm"
        label="Breite (mm)"
        type="number"
        :value="$value('width_mm')"
        :required="true"
        min="1"
        col-class="col-md-4"
    />
    <x-forms.input
        name="height_mm"
        label="Höhe (mm)"
        type="number"
        :value="$value('height_mm')"
        :required="true"
        min="1"
        col-class="col-md-4"
    />
    <x-forms.input
        name="truck_slot_units"
        label="Slots"
        type="number"
        :value="$value('truck_slot_units')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="max_units_per_pallet_same_recipient"
        label="Max / Empfänger"
        type="number"
        :value="$value('max_units_per_pallet_same_recipient')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="max_units_per_pallet_mixed_recipient"
        label="Max / Mix"
        type="number"
        :value="$value('max_units_per_pallet_mixed_recipient')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="max_stackable_pallets_same_recipient"
        label="Stapel (Empfänger)"
        type="number"
        :value="$value('max_stackable_pallets_same_recipient')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="max_stackable_pallets_mixed_recipient"
        label="Stapel (Mix)"
        type="number"
        :value="$value('max_stackable_pallets_mixed_recipient')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.textarea
        name="notes"
        label="Hinweise"
        :value="$value('notes')"
        :rows="3"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.packaging.index')"
        />
    </x-slot>
</x-forms.form>
