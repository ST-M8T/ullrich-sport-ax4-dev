@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentVariationProfile|null $profile */
    $profile = $profile ?? null;
    $packagingProfiles = collect($packagingProfiles ?? []);
    $assemblyOptions = collect($assemblyOptions ?? []);
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';

    $fieldMappers = [
        'default_packaging_id' => fn($p) => $p->defaultPackagingId()?->toInt(),
        'assembly_option_id' => fn($p) => $p->assemblyOptionId()?->toInt(),
    ];

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $profile, $fieldMappers);

    $stateOptions = [
        'assembled' => 'Vormontiert',
        'kit' => 'Bausatz',
    ];

    $packagingOptions = $packagingProfiles->mapWithKeys(fn($p) => [$p->id()->toInt() => $p->packageName()])->all();
    $assemblyOptionsList = $assemblyOptions->mapWithKeys(fn($opt) => [$opt->id()->toInt() => 'Artikel ' . $opt->assemblyItemId()])->all();
    $assemblyOptionsList = ['' => 'Keine'] + $assemblyOptionsList;
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="item_id"
        label="Item-ID"
        type="number"
        :value="$value('item_id')"
        :required="true"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="variation_id"
        label="Varianten-ID"
        type="number"
        :value="$value('variation_id')"
        min="1"
        col-class="col-md-3"
    />
    <x-forms.input
        name="variation_name"
        label="Name"
        type="text"
        :value="$value('variation_name')"
        col-class="col-md-6"
    />
    <x-forms.select
        name="default_state"
        label="Standardzustand"
        :options="$stateOptions"
        :value="$value('default_state')"
        :required="true"
        col-class="col-md-4"
    />
    <x-forms.select
        name="default_packaging_id"
        label="Standard-Verpackung"
        :options="$packagingOptions"
        :value="$value('default_packaging_id')"
        :required="true"
        col-class="col-md-4"
    />
    <x-forms.input
        name="default_weight_kg"
        label="Standardgewicht (kg)"
        type="number"
        :value="$value('default_weight_kg')"
        step="0.01"
        min="0"
        col-class="col-md-4"
    />
    <x-forms.select
        name="assembly_option_id"
        label="Vormontage-Option"
        :options="$assemblyOptionsList"
        :value="$value('assembly_option_id')"
        placeholder=""
        col-class="col-md-6"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.variations.index')"
        />
    </x-slot>
</x-forms.form>
