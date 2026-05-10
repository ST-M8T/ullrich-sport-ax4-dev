<x-tabs
    :tabs="$tabs"
    :active-tab="$activeTab"
    :base-url="route('configuration-settings', $baseParameters ?? [])"
    tab-param="tab"
    aria-label="Systemeinstellungsbereiche"
/>
