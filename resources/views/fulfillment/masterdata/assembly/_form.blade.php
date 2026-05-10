@php
    use App\View\Helpers\DomainFormHelper;

    /** @var \App\Domain\Fulfillment\Masterdata\FulfillmentAssemblyOption|null $option */
    $option = $option ?? null;
    $action = $action ?? '';
    $method = $method ?? null;
    $submitLabel = $submitLabel ?? 'Speichern';
    $packagingProfiles = collect($packagingProfiles ?? []);

    $fieldMappers = [
        'assembly_packaging_id' => fn($o) => $o->assemblyPackagingId()?->toInt(),
    ];

    $value = fn(string $field, $fallback = null) => DomainFormHelper::value($field, $fallback, $option, $fieldMappers);

    $packagingOptions = $packagingProfiles->mapWithKeys(fn($p) => [
        $p->id()->toInt() => $p->packageName() . ' (' . ($p->packagingCode() ?? 'ohne Code') . ')'
    ])->all();
@endphp

<x-forms.form :action="$action" :method="$method">
    <x-forms.input
        name="assembly_item_id"
        label="Vormontage-Artikel-ID"
        type="number"
        :value="$value('assembly_item_id')"
        :required="true"
        min="1"
        col-class="col-md-4"
    />
    <x-forms.select
        name="assembly_packaging_id"
        label="Verpackungsprofil"
        :options="$packagingOptions"
        :value="$value('assembly_packaging_id')"
        :required="true"
        col-class="col-md-4"
    />
    <x-forms.input
        name="assembly_weight_kg"
        label="Gewicht (kg)"
        type="number"
        :value="$value('assembly_weight_kg')"
        step="0.01"
        min="0"
        col-class="col-md-4"
    />
    <x-forms.input
        name="description"
        label="Beschreibung"
        type="text"
        :value="$value('description')"
        col-class="col-12"
    />

    <x-slot name="actions">
        <x-forms.form-actions
            :submit-label="$submitLabel"
            :cancel-url="route('fulfillment.masterdata.assembly.index')"
        />
    </x-slot>
</x-forms.form>
