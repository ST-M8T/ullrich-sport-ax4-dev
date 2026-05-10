@props([
    'title' => 'Aktionen',
])

<div class="card h-100">
    <div class="card-body">
        <h2 class="h5 mb-3">{{ $title }}</h2>
        {{ $slot }}
    </div>
</div>

