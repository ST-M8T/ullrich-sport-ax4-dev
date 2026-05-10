@props([
    'currentSection' => '',
    'items' => null,
    'navigationItems' => [],
])

@php
    $listAttributes = $attributes->class('admin-nav__list');
@endphp

<ul {{ $listAttributes }}>
    @foreach($navigationItems as $item)
        @if(!empty($item['children']))
            <li class="admin-nav__group {{ !empty($item['active']) ? 'is-active' : '' }}">
                <h3 class="admin-nav__group-label">{{ $item['label'] }}</h3>
                <ul class="admin-nav__sublist">
                    @foreach($item['children'] as $child)
                        @if(!empty($child['href']))
                            <li class="admin-nav__item admin-nav__item--sub">
                                <a
                                    href="{{ $child['href'] }}"
                                    class="admin-nav__link admin-nav__link--sub {{ !empty($child['active']) ? 'is-active' : '' }}"
                                    @if(!empty($child['active']))
                                        aria-current="page"
                                    @endif
                                >
                                    {{ $child['label'] }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </li>
        @elseif(!empty($item['href']))
            <li class="admin-nav__item">
                <a
                    href="{{ $item['href'] }}"
                    class="admin-nav__link {{ !empty($item['active']) ? 'is-active' : '' }}"
                    @if(!empty($item['active']))
                        aria-current="page"
                    @endif
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endif
    @endforeach
</ul>
