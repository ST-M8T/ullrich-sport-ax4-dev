@props(['items' => []])

@php
    $normalized = collect($items)
        ->map(function ($item) {
            if (is_string($item)) {
                return ['label' => trim($item), 'url' => null];
            }

            if (is_array($item)) {
                return [
                    'label' => trim((string) ($item['label'] ?? $item['title'] ?? '')),
                    'url' => $item['url'] ?? $item['href'] ?? null,
                ];
            }

            if (is_object($item) && method_exists($item, 'label')) {
                return [
                    'label' => trim((string) $item->label()),
                    'url' => method_exists($item, 'url') ? $item->url() : null,
                ];
            }

            return null;
        })
        ->filter(fn ($item) => filled($item) && filled($item['label'] ?? null))
        ->values();
@endphp

@if($normalized->isNotEmpty())
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ol class="flex items-center gap-2">
            @foreach($normalized as $item)
                <li class="breadcrumbs__item">
                    @if($loop->last || empty($item['url']))
                        <span aria-current="{{ $loop->last ? 'page' : 'false' }}">
                            {{ $item['label'] }}
                        </span>
                    @else
                        <a href="{{ $item['url'] }}" class="breadcrumbs__link">
                            {{ $item['label'] }}
                        </a>
                    @endif

                    @unless($loop->last)
                        <span aria-hidden="true" class="breadcrumbs__separator">/</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>
@endif
