@if(empty($availableTools))
    <x-ui.empty-state
        title="Keine Log-Werkzeuge"
        description="Für Ihr Benutzerkonto sind aktuell keine Log-Ansichten freigeschaltet."
    />
@else
    @php
        $logTabsArray = collect($logTabs)->mapWithKeys(function ($tab, $key) {
            return [$key => $tab['label']];
        })->all();
    @endphp

    <x-tabs
        :tabs="$logTabsArray"
        :active-tab="$activeLogTab"
        :base-url="route('configuration-settings', array_merge(request()->query(), ['tab' => 'logs']))"
        tab-param="log_tab"
        aria-label="Log-Analyse"
        class="mb-4"
    />

    @foreach($processedTools as $tool)
        @if($activeLogTab === $tool['key'])
            <div>
                @if(isset($tool['full_view']) && isset($tool['full_view_data']))
                    @include($tool['full_view'], $tool['full_view_data'])
                @elseif(isset($tool['view']))
                    @include($tool['view'], $tool['view_data'] ?? [])
                @else
                    <x-ui.empty-state
                        title="Bereich nicht verfügbar"
                        description="Für dieses Tool wurde keine View registriert."
                    />
                @endif
            </div>
        @endif
    @endforeach
@endif
