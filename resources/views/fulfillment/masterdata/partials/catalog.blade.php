@if(empty($hideHeading))
    <div class="mb-3">
        <h2 class="h5 mb-1">Fulfillment-Stammdaten</h2>
        <p class="text-muted mb-0 small">Thematisch organisiert: Versand (Verpackungen, Versandprofile, Versender, Versender-Regeln) und Artikel (Varianten, Vormontage).</p>
        <div class="mt-2 text-muted small">
            Datenquelle: <code>GetFulfillmentMasterdataCatalog</code><br>
            Export- und Detailfunktionen gelten pro Sektion.
        </div>
    </div>
@endif

@foreach($masterdataTabGroups as $group)
    <div class="mb-4">
        <h3 class="h6 mb-2 text-muted">{{ $group['label'] }}</h3>
        <x-tabs
            :tabs="$group['tabs']"
            :active-tab="$activeTab"
            :base-url="request()->url()"
            :tab-param="$masterdataTabParam"
            aria-label="{{ $group['label'] }}-Stammdaten"
            class="mb-3"
        />
    </div>
@endforeach

@if(empty($masterdataTabGroups))
    <x-tabs
        :tabs="$masterdataTabs"
        :active-tab="$activeTab"
        :base-url="request()->url()"
        :tab-param="$masterdataTabParam"
        aria-label="Stammdaten-Bereiche"
        class="mb-4"
    />
@endif

@if($activeTab === 'packaging')
    @include('fulfillment.masterdata.sections.packaging', ['catalog' => $catalog, 'count' => $packagingCount])
@elseif($activeTab === 'assembly')
    @include('fulfillment.masterdata.sections.assembly', ['catalog' => $catalog, 'count' => $assemblyCount])
@elseif($activeTab === 'variations')
    @include('fulfillment.masterdata.sections.variations', ['catalog' => $catalog, 'count' => $variationCount])
@elseif($activeTab === 'sender')
    @include('fulfillment.masterdata.sections.senders', ['catalog' => $catalog, 'count' => $senderCount])
@elseif($activeTab === 'sender-rules')
    @include('fulfillment.masterdata.sections.sender-rules', ['catalog' => $catalog, 'count' => $senderRulesCount])
@elseif($activeTab === 'freight')
    @include('fulfillment.masterdata.sections.freight', ['catalog' => $catalog, 'count' => $freightCount])
@endif
