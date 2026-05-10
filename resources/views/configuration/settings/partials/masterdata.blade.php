@if(isset($catalog))
    @include('fulfillment.masterdata.partials.catalog', [
        'catalog' => $catalog,
        'hideHeading' => true,
        'masterdataTabParam' => 'masterdata_sub_tab',
    ])
@else
    <x-ui.empty-state
        title="Keine Stammdaten"
        description="Der Stammdaten-Katalog konnte nicht geladen werden."
    />
@endif
