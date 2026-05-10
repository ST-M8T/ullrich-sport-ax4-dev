@props([
    'action' => '',
    'method' => null,
    'novalidate' => true,
])

<form method="post" action="{{ $action }}" @if($novalidate) novalidate @endif>
    @csrf
    @if($method)
        @method($method)
    @endif

    <div class="row g-4">
        {{ $slot }}
    </div>

    @isset($actions)
        {{ $actions }}
    @endisset
</form>

