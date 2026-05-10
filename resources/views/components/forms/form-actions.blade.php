@props([
    'submitLabel' => 'Speichern',
    'cancelUrl' => null,
    'cancelLabel' => 'Abbrechen',
])

<div class="mt-4 d-flex justify-content-end gap-2">
    @if(isset($cancel))
        {{ $cancel }}
    @elseif($cancelUrl)
        <a href="{{ $cancelUrl }}" class="btn btn-link">{{ $cancelLabel }}</a>
    @endif
    @if(isset($slot) && !$slot->isEmpty())
        {{ $slot }}
    @else
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    @endif
</div>

