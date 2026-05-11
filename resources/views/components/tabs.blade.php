@props([
    'tabs' => [],
    'activeTab' => null,
    'baseUrl' => null,
    'tabParam' => 'tab',
    'ariaLabel' => 'Tab-Navigation',
    'class' => '',
    'processedTabs' => [],
])

<nav class="tabs {{ $class }}" aria-label="{{ $ariaLabel }}">
    @foreach($processedTabs as $processed)
        <a
            href="{{ $processed['url'] }}"
            class="tabs__button {{ $processed['isActive'] ? 'is-active' : '' }}"
            @if($processed['isActive']) aria-selected="true" @endif
        >
            {{ $processed['label'] }}
            @if($processed['badge'] !== null)
                <span class="tabs__badge">{{ $processed['badge'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
