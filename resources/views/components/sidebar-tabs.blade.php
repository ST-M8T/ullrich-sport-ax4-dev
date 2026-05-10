@props([
    'tabs' => [],
    'activeTab' => null,
    'baseUrl' => null,
    'tabParam' => 'tab',
    'title' => null,
    'description' => null,
    'class' => '',
    'cleanBaseUrl' => null,
    'queryParams' => [],
    'processedTabs' => [],
])

<div class="sidebar-tabs {{ $class }}">
    @if($title || $description)
        <div class="sidebar-tabs__header">
            @if($title)
                <h2 class="sidebar-tabs__title">{{ $title }}</h2>
            @endif
            @if($description)
                <p class="sidebar-tabs__description">{{ $description }}</p>
            @endif
        </div>
    @endif

    <nav class="sidebar-tabs__nav" role="navigation" aria-label="Seitennavigation">
        <ul class="sidebar-tabs__list">
            @foreach($processedTabs as $processed)
                <li class="sidebar-tabs__item">
                    <a
                        href="{{ $processed['url'] }}"
                        class="sidebar-tabs__link {{ $processed['isActive'] ? 'is-active' : '' }}"
                        @if($processed['isActive']) aria-current="page" @endif
                    >
                        <span class="sidebar-tabs__link-text">{{ $processed['label'] }}</span>
                        @if($processed['badge'] !== null)
                            <span class="sidebar-tabs__badge">{{ $processed['badge'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</div>

