@props([
    'name',
    'label',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'rows' => 3,
    'class' => '',
    'colClass' => null,
    'idSuffix' => null,
])

@php
    $fieldId = $idSuffix !== null ? $name . '_' . $idSuffix : $name;
    $errorId = $fieldId . '-error';
    $fieldValue = old($name, $value);
    $hasError = $errors->has($name);
    $errorClass = $hasError ? 'is-invalid' : '';
    $textareaClass = trim("form-control {$errorClass} {$class}");
    $colClass = $colClass ?? 'col-12';
@endphp

<div class="{{ $colClass }}">
    <label class="form-label" for="{{ $fieldId }}">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        class="{{ $textareaClass }}"
        @if($required) required aria-required="true" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->except(['class', 'colClass']) }}
    >{{ $fieldValue }}</textarea>
    @error($name)
        <div id="{{ $errorId }}" class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
