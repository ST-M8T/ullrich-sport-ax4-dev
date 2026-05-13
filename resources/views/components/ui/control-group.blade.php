@props([
    'tag' => 'div',
])
<{{ $tag }} {{ $attributes->class(['d-flex gap-2 align-items-center']) }}>
    {{ $slot }}
</{{ $tag }}>
