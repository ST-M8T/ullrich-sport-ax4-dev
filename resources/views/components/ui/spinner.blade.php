@props([
    'message' => 'Lade Daten...',
    'show' => true,
])

@if($show)
    <div class="ui-spinner" role="status" aria-live="polite">
        <span class="ui-spinner__icon" aria-hidden="true"></span>
        <span>{{ $message }}</span>
    </div>
@endif
