@props([
    'tabs' => [],
    'activeTab' => null,
    'baseUrl' => null,
    'queryParams' => [],
    'processedTabs' => [],
])

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($processedTabs as $processed)
        <a href="{{ $processed['url'] }}" class="btn btn-sm {{ $processed['isActive'] ? 'btn-primary' : 'btn-outline-primary' }}">
            {{ $processed['label'] }}
        </a>
    @endforeach
</div>

