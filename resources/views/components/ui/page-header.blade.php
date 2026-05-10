@props([
    'title' => null,
    'subtitle' => null,
])

{{-- Wiederverwendbarer Page-Header.
     Slot: actions (optional, rechts platziert).
     Slot: default (optional, ersetzt $title für komplexere Inhalte). --}}
<div {{ $attributes->class('d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4')->merge() }}>
    <div>
        @if($slot->isNotEmpty())
            {{ $slot }}
        @elseif($title !== null)
            <h1 class="mb-0">{{ $title }}</h1>
        @endif
        @if($subtitle !== null)
            <p class="text-muted mb-0 small">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="page-header__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
