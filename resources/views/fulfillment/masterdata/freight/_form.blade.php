@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile|null $profile */
    $profile = $profile ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';

    $fieldMappers = [
        'shipping_profile_id' => fn($p) => $p->shippingProfileId()?->toInt(),
    ];

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $profile, $fieldMappers);
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="shipping_profile_id"
        label="Versandprofil-ID"
        type="number"
        :value="$value('shipping_profile_id')"
        :required="true"
        min="1"
        :readonly="$profile !== null"
        :tabindex="$profile ? -1 : null"
        col-class="col-md-4"
    />
    @if($profile)
        <input type="hidden" name="shipping_profile_id" value="{{ $value('shipping_profile_id') }}">
    @endif
    <x-forms.input
        name="label"
        label="Bezeichnung"
        type="text"
        :value="$value('label')"
        col-class="col-md-8"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.freight.index')"
        />
    </x-slot>
</x-forms.form>
