@props([
    'title' => null,
    'header' => false,
])

<div class="card">
    @if($header)
        <div class="card-header">{{ $title ?? $header }}</div>
    @endif
    <div class="card-body">
        @if($title && !$header)
            <h2 class="h5 mb-3">{{ $title }}</h2>
        @endif
        {{ $slot }}
    </div>
</div>
