@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentSenderProfile|null $profile */
    $profile = $profile ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $profile);
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="sender_code"
        label="Sender-Code"
        type="text"
        :value="$value('sender_code')"
        :required="true"
        col-class="col-md-3"
    />
    <x-forms.input
        name="display_name"
        label="Anzeigename"
        type="text"
        :value="$value('display_name')"
        :required="true"
        col-class="col-md-4"
    />
    <x-forms.input
        name="company_name"
        label="Firma"
        type="text"
        :value="$value('company_name')"
        :required="true"
        col-class="col-md-5"
    />
    <x-forms.input
        name="contact_person"
        label="Ansprechpartner"
        type="text"
        :value="$value('contact_person')"
        col-class="col-md-4"
    />
    <x-forms.input
        name="email"
        label="E-Mail"
        type="email"
        :value="$value('email')"
        col-class="col-md-4"
    />
    <x-forms.input
        name="phone"
        label="Telefon"
        type="text"
        :value="$value('phone')"
        col-class="col-md-4"
    />
    <x-forms.input
        name="street_name"
        label="Straße"
        type="text"
        :value="$value('street_name')"
        :required="true"
        col-class="col-md-5"
    />
    <x-forms.input
        name="street_number"
        label="Hausnummer"
        type="text"
        :value="$value('street_number')"
        col-class="col-md-2"
    />
    <x-forms.input
        name="address_addition"
        label="Adresszusatz"
        type="text"
        :value="$value('address_addition')"
        col-class="col-md-5"
    />
    <x-forms.input
        name="postal_code"
        label="PLZ"
        type="text"
        :value="$value('postal_code')"
        :required="true"
        col-class="col-md-3"
    />
    <x-forms.input
        name="city"
        label="Stadt"
        type="text"
        :value="$value('city')"
        :required="true"
        col-class="col-md-5"
    />
    <x-forms.input
        name="country_iso2"
        label="Land (ISO)"
        type="text"
        :value="strtoupper($value('country_iso2', 'DE'))"
        :required="true"
        maxlength="2"
        class="text-uppercase"
        col-class="col-md-2"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.senders.index')"
        />
    </x-slot>
</x-forms.form>
