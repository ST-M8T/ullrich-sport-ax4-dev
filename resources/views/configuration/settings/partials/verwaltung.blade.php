@if(empty($availableVerwaltung))
    <x-ui.empty-state
        title="Keine Verwaltungsbereiche verfügbar"
        description="Für Ihr Benutzerkonto stehen derzeit keine Verwaltungsbereiche bereit."
    />
@else
    @php
        $verwaltungTabsArray = collect($verwaltungTabs)->mapWithKeys(function ($tab, $key) {
            return [$key => $tab['label']];
        })->all();
    @endphp

    <x-tabs
        :tabs="$verwaltungTabsArray"
        :active-tab="$activeVerwaltung"
        :base-url="route('configuration-settings', array_merge(request()->query(), ['tab' => 'verwaltung']))"
        tab-param="verwaltung_tab"
        aria-label="Verwaltungsbereiche"
        class="mb-4"
    />

    @foreach($processedVerwaltung as $item)
        @if($activeVerwaltung === $item['key'])
            <div>
                @if(isset($item['full_view']) && isset($item['full_view_data']))
                    @include($item['full_view'], $item['full_view_data'])
                @elseif(isset($item['view']))
                    @include($item['view'], $item['view_data'] ?? [])
                @else
                    <x-ui.empty-state
                        title="Bereich nicht verfügbar"
                        description="Für diesen Verwaltungsbereich wurde keine View registriert."
                    />
                @endif
            </div>
        @endif
    @endforeach
@endif


