@props([
    'name',
    'label',
    'value' => null,
    'checked' => false,
    'class' => '',
    'colClass' => null,
    'idSuffix' => null,
    'switch' => false,
])

@php
    $fieldId = $idSuffix !== null ? $name . '_' . $idSuffix : $name;
    $errorId = $fieldId . '-error';
    $isChecked = old($name, $value) || $checked;
    $hasError = $errors->has($name);
    $colClass = $colClass ?? 'col-12';
    $wrapperClass = $switch ? 'form-check form-switch' : 'form-check';
@endphp

<div class="{{ $colClass }}">
    <div class="{{ $wrapperClass }}">
        <input
            id="{{ $fieldId }}"
            type="checkbox"
            name="{{ $name }}"
            value="1"
            class="form-check-input {{ $hasError ? 'is-invalid' : '' }} {{ $class }}"
            @if($switch) role="switch" @endif
            @checked($isChecked)
            @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
            {{ $attributes->except(['class', 'colClass']) }}
        >
        <label class="form-check-label" for="{{ $fieldId }}">
            {{ $label }}
        </label>
        @error($name)
            <div id="{{ $errorId }}" class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
