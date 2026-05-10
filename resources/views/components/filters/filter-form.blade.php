@props([
    'action' => null,
    'method' => 'GET',
    'filters' => [],
    'hiddenFields' => [],
    'hideActions' => false,
    'resolvedAction' => null,
])

<form method="{{ $method }}" action="{{ $resolvedAction ?? $action ?? request()->url() }}" {{ $attributes->merge(['class' => 'row g-3 align-items-end']) }}>
    @if($method === 'POST')
        @csrf
    @endif

    @foreach($hiddenFields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach

    {{ $slot }}

    @if(!isset($hideActions) || !$hideActions)
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary text-uppercase">FILTERN</button>
            <a href="{{ $resolvedAction ?? $action ?? request()->url() }}" class="btn btn-outline-secondary text-uppercase">ZURÜCKSETZEN</a>
        </div>
    @endif
</form>

