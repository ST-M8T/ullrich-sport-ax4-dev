@props([
    'href' => null,
    'variant' => 'outline-primary',
    'size' => 'sm',
])

{{-- Wiederverwendbarer kleiner Action-Link / Button im Section-Stil.
     - href: optional. Wenn gesetzt → <a>; sonst → <button>.
     - variant: Bootstrap-Button-Variant (z.B. outline-primary, outline-secondary, primary, secondary).
     - size: Bootstrap-Button-Größe (sm, lg, oder leer). --}}
@php
    $sizeClass = $size ? "btn-{$size}" : '';
    $base = trim("btn btn-{$variant} {$sizeClass} text-uppercase");
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($base)->merge() }}>
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->class($base)->merge() }}>
        {{ $slot }}
    </button>
@endif
