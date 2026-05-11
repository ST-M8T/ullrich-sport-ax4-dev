@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentFreightProfile|null $profile */
    $profile = $profile ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';

    $fieldMappers = [
        'shipping_profile_id' => fn($p) => $p->shippingProfileId()?->toInt(),
        'dhl_product_id' => fn($p) => $p->dhlProductId(),
        'dhl_default_service_codes' => fn($p) => $p->dhlDefaultServiceCodes(),
        'shipping_method_mapping' => fn($p) => $p->shippingMethodMapping(),
        'account_number' => fn($p) => $p->accountNumber(),
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
    <x-forms.input
        name="dhl_product_id"
        label="DHL-Produkt-ID"
        type="text"
        :value="$value('dhl_product_id')"
        placeholder="z.B. V2PK"
        col-class="col-md-4"
    />
    <x-forms.input
        name="account_number"
        label="DHL-Kundennummer"
        type="text"
        :value="$value('account_number')"
        placeholder="123456789"
        col-class="col-md-4"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.freight.index')"
        />
    </x-slot>
</x-forms.form>
