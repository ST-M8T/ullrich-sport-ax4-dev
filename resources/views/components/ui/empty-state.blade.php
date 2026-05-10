@props([
    'title' => 'Keine Daten vorhanden',
    'description' => null,
    'icon' => null,
    'actions' => [],
])

@php
    $normalizedActions = collect($actions)
        ->map(function ($action, $key) {
            if (is_string($action)) {
                return [
                    'label' => $action,
                    'url' => is_string($key) ? $key : null,
                    'style' => 'primary',
                ];
            }

            if (is_array($action)) {
                return [
                    'label' => $action['label'] ?? $action['text'] ?? '',
                    'url' => $action['url'] ?? $action['href'] ?? null,
                    'style' => $action['style'] ?? $action['variant'] ?? 'primary',
                ];
            }

            return null;
        })
        ->filter(fn ($action) => filled($action) && filled($action['label'] ?? null))
        ->values();
@endphp

<div class="empty-state" role="status">
    <div class="empty-state__icon" aria-hidden="true">
        @if($icon instanceof \Illuminate\View\ComponentSlot)
            {{ $icon }}
        @elseif(is_string($icon) && $icon !== '')
            <span>{{ $icon }}</span>
        @else
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7h18"></path>
                <path d="M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7"></path>
                <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
            </svg>
        @endif
    </div>

    <div>
        <h3 class="text-base font-semibold text-slate-800">{{ $title }}</h3>
        @if(filled($description))
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if($normalizedActions->isNotEmpty())
        <div class="empty-state__actions">
            @foreach($normalizedActions as $action)
                @php
                    $style = match ($action['style']) {
                        'secondary' => 'btn btn-secondary',
                        'outline' => 'btn btn-outline',
                        'success' => 'btn btn-success',
                        'info' => 'btn btn-info',
                        default => 'btn btn-primary',
                    };
                @endphp
                @if(filled($action['url']))
                    <a href="{{ $action['url'] }}" class="{{ $style }}">{{ $action['label'] }}</a>
                @else
                    <span class="{{ $style }}">{{ $action['label'] }}</span>
                @endif
            @endforeach
        </div>
    @endif
</div>
