@props([
    'title' => null,
    'id' => null,
    'size' => 'md',
])

@php
$sizeClass = match($size) {
    'sm' => 'modal-sm',
    'lg' => 'modal-lg',
    'xl' => 'modal-xl',
    default => '',
};
@endphp

<div class="modal fade"
     id="{{ $id }}"
     tabindex="-1"
     aria-labelledby="{{ $id }}Label"
     aria-hidden="true"
     x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $id }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $id }}') show = false"
>
    <div class="modal-dialog {{ $sizeClass }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
