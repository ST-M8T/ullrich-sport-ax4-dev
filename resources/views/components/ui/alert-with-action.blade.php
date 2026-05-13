@props([
    'variant' => 'info',
])
<div {{ $attributes->class(['alert d-flex justify-content-between align-items-center', 'alert-' . $variant]) }}>
    {{ $slot }}
</div>
