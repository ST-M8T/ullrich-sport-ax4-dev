@props([
    'title' => null,
    'description' => null,
    'count' => null,
])

{{-- Wiederverwendbarer Section-Header für Cards/Sections.
     - title: Pflichttext, gerendert als <h2 class="h5"> mit optionaler count-Badge
     - description: optionale Beschreibung darunter
     - Slot: actions (optional, rechts) — typischerweise Aktion-Links/Buttons
     - Slot: default — fallback wenn die Standard-Struktur nicht reicht --}}
<div {{ $attributes->class('d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3')->merge() }}>
    <div>
        @if($slot->isNotEmpty())
            {{ $slot }}
        @else
            <h2 class="h5 mb-1">
                {{ $title }}
                @if($count !== null)
                    <span class="badge bg-light text-dark ms-2">{{ $count }}</span>
                @endif
            </h2>
            @if($description !== null)
                <p class="text-muted mb-0 small">{{ $description }}</p>
            @endif
        @endif
    </div>
    @isset($actions)
        <div class="section-header__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
