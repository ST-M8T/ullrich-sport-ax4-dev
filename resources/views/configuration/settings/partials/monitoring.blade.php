@if(empty($availableMonitoring))
    <x-ui.empty-state
        title="Keine Monitoring-Bereiche verfügbar"
        description="Für Ihr Benutzerkonto stehen derzeit keine Monitoring-Bereiche bereit."
    />
@else
    @php
        $monitoringTabsArray = collect($monitoringTabs)->mapWithKeys(function ($tab, $key) {
            return [$key => $tab['label']];
        })->all();
    @endphp

    <x-tabs
        :tabs="$monitoringTabsArray"
        :active-tab="$activeMonitoring"
        :base-url="route('configuration-settings', array_merge(request()->query(), ['tab' => 'monitoring']))"
        tab-param="monitoring_tab"
        aria-label="Monitoring-Bereiche"
        class="mb-4"
    />

    @foreach($processedMonitoring as $item)
        @if($activeMonitoring === $item['key'])
            <div>
                @if(isset($item['full_view']) && isset($item['full_view_data']))
                    @include($item['full_view'], $item['full_view_data'])
                @elseif(isset($item['view']))
                    @include($item['view'], $item['view_data'] ?? [])
                @else
                    <x-ui.empty-state
                        title="Bereich nicht verfügbar"
                        description="Für diesen Monitoring-Bereich wurde keine View registriert."
                    />
                @endif
            </div>
        @endif
    @endforeach
@endif


